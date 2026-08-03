<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Queue;
use App\Services\CartService;
use App\Services\OrderManagementService;
use App\Services\ProductEnrichmentService;
use App\Services\StockService;

/*
|--------------------------------------------------------------------------
| OrderManagementService
|--------------------------------------------------------------------------
|
| È una facade: ogni metodo deve restituire esattamente quello che
| restituirebbe il servizio sottostante chiamato con gli stessi argomenti.
| Nessun mock: si confrontano i risultati reali dei due percorsi.
|
*/

beforeEach(function () {
    $this->manager = app(OrderManagementService::class);
    $this->cart = new CartService;
    $this->stock = new StockService;
    $this->enrichment = app(ProductEnrichmentService::class);
});

/**
 * @return array{item_id: string, product_id: int, quantity: int, note: string|null}
 */
function facadeCartRow(int $productId, int $quantity = 1, ?string $note = null): array
{
    return [
        'item_id' => 'riga-'.$productId.'-'.$quantity,
        'product_id' => $productId,
        'quantity' => $quantity,
        'note' => $note,
    ];
}

/**
 * Righe senza item_id: le nuove righe hanno un uuid casuale, non confrontabile.
 *
 * @param  array<int, array<string, mixed>>  $items
 * @return array<int, array<string, mixed>>
 */
function senzaItemId(array $items): array
{
    return array_map(static function (array $item): array {
        unset($item['item_id']);

        return $item;
    }, $items);
}

// ==================== Cart ====================

it('delega addProduct al CartService', function () {
    $items = [facadeCartRow(1, 2)];

    $viaFacade = $this->manager->addProduct($items, 5);

    expect(senzaItemId($viaFacade))->toBe(senzaItemId($this->cart->addProduct($items, 5)))
        ->and($viaFacade)->toHaveCount(2)
        ->and($viaFacade[1]['product_id'])->toBe(5)
        ->and($viaFacade[1]['quantity'])->toBe(1);
});

it('delega removeProduct al CartService', function () {
    $items = [facadeCartRow(1), facadeCartRow(2), facadeCartRow(3)];

    $viaFacade = $this->manager->removeProduct($items, 1);

    expect($viaFacade)->toBe($this->cart->removeProduct($items, 1))
        ->and(array_column($viaFacade, 'product_id'))->toBe([1, 3]);
});

it('delega increaseQuantity al CartService', function () {
    $items = [facadeCartRow(1, 2)];

    $viaFacade = $this->manager->increaseQuantity($items, 0);

    expect($viaFacade)->toBe($this->cart->increaseQuantity($items, 0))
        ->and($viaFacade[0]['quantity'])->toBe(3);
});

it('delega decreaseQuantity al CartService', function () {
    $items = [facadeCartRow(1, 2)];

    $viaFacade = $this->manager->decreaseQuantity($items, 0);

    expect($viaFacade)->toBe($this->cart->decreaseQuantity($items, 0))
        ->and($viaFacade[0]['quantity'])->toBe(1);
});

it('delega splitItem al CartService', function () {
    $items = [facadeCartRow(1, 3)];

    $viaFacade = $this->manager->splitItem($items, 0);

    expect(senzaItemId($viaFacade))->toBe(senzaItemId($this->cart->splitItem($items, 0)))
        ->and(array_column($viaFacade, 'quantity'))->toBe([2, 1]);
});

it('delega getTotalItemsCount al CartService', function () {
    $items = [facadeCartRow(1, 2), facadeCartRow(2, 5)];

    expect($this->manager->getTotalItemsCount($items))
        ->toBe($this->cart->getTotalItemsCount($items))
        ->toBe(7);
});

it('delega getOrderTotal al CartService', function () {
    $panino = Product::factory()->create(['price' => 4.5]);
    $piadina = Product::factory()->create(['price' => 2.25]);

    $items = [facadeCartRow($panino->id, 2), facadeCartRow($piadina->id, 4)];

    expect($this->manager->getOrderTotal($items))
        ->toBe($this->cart->getOrderTotal($items))
        ->toBe(18.0);
});

// ==================== Stock ====================

it('delega hasOutOfStockItems allo StockService', function () {
    $product = Product::factory()->create(['stock' => 2]);

    $sfora = [facadeCartRow($product->id, 3)];
    $rientra = [facadeCartRow($product->id, 2)];

    expect($this->manager->hasOutOfStockItems($sfora))
        ->toBe((new StockService)->hasOutOfStockItems($sfora))
        ->toBeTrue()
        ->and($this->manager->hasOutOfStockItems($rientra))
        ->toBe((new StockService)->hasOutOfStockItems($rientra))
        ->toBeFalse();
});

// ==================== Enrichment ====================

it('delega getEnrichedProduct al ProductEnrichmentService', function () {
    $product = Product::factory()->create(['stock' => 9]);
    $items = [facadeCartRow($product->id, 4)];

    $viaFacade = $this->manager->getEnrichedProduct($items, $product, 3, 11);

    expect($viaFacade)->toBe($this->enrichment->getEnrichedProduct($items, $product, 3, 11))
        ->and($viaFacade['number'])->toBe(4)
        ->and($viaFacade['remaining_stock'])->toBe(5)
        ->and($viaFacade['sold'])->toBe(11);
});

it('delega getSoldSinceQueueReset al ProductEnrichmentService', function () {
    $product = Product::factory()->create();
    $coda = Queue::factory()->create(['reset_at' => null]);
    $order = Order::factory()->for($coda)->create();
    OrderItem::factory()->of($product, 6)->for($order)->create();

    expect($this->manager->getSoldSinceQueueReset([$product->id]))
        ->toBe($this->enrichment->getSoldSinceQueueReset([$product->id]))
        ->toBe([$product->id => 6]);
});

it('delega getSortedEnrichedItems al ProductEnrichmentService', function () {
    $primo = Product::factory()->create(['stock' => 50]);
    $secondo = Product::factory()->create(['stock' => 50]);

    $items = [facadeCartRow($primo->id), facadeCartRow($secondo->id)];
    $numeri = [$primo->id => 2, $secondo->id => 1];

    $viaFacade = $this->manager->getSortedEnrichedItems($items, $numeri);
    $viaServizio = $this->enrichment->getSortedEnrichedItems($items, $numeri);

    expect(array_column($viaFacade, 'original_index'))->toBe([1, 0])
        ->and(array_column($viaFacade, 'sort_order'))->toBe(array_column($viaServizio, 'sort_order'))
        ->and(array_column($viaFacade, 'item_id'))->toBe(array_column($viaServizio, 'item_id'))
        ->and(array_column($viaFacade, 'row_total'))->toBe(array_column($viaServizio, 'row_total'));
});
