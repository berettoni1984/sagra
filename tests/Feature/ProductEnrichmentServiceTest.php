<?php

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Queue;
use App\Services\ProductEnrichmentService;

beforeEach(function () {
    $this->enrichment = app(ProductEnrichmentService::class);
});

/**
 * Riga di carrello nella forma usata dalla cassa.
 *
 * @return array{item_id: string, product_id: int, quantity: int, note: string|null}
 */
function enrichCartRow(int $productId, int $quantity = 1, ?string $note = null): array
{
    return [
        'item_id' => 'riga-'.$productId.'-'.$quantity.'-'.uniqid(),
        'product_id' => $productId,
        'quantity' => $quantity,
        'note' => $note,
    ];
}

/**
 * Crea una riga d'ordine di un prodotto su una coda, a una data precisa.
 */
function vendita(Product $product, Queue $queue, int $quantity, string|DateTimeInterface $quando): OrderItem
{
    $order = Order::factory()->for($queue)->createdAt($quando)->create();

    return OrderItem::factory()->of($product, $quantity)->for($order)->create();
}

// ==================== getEnrichedProduct ====================

it('arricchisce un prodotto con numero progressivo e venduti', function () {
    $product = Product::factory()->create(['name' => 'Panino', 'price' => 5.5, 'stock' => 10]);

    $items = [enrichCartRow($product->id, 2), enrichCartRow($product->id, 1)];

    $enriched = $this->enrichment->getEnrichedProduct($items, $product, 2, 42);

    expect(array_keys($enriched))->toBe([
        'id', 'name', 'price', 'stock', 'backorder', 'number',
        'total_in_cart', 'remaining_stock', 'remaining_units', 'is_out_of_stock',
        'is_low_stock', 'has_insufficient_ingredients', 'has_low_ingredients', 'sold',
    ])
        ->and($enriched['id'])->toBe($product->id)
        ->and($enriched['name'])->toBe('Panino')
        ->and((float) $enriched['price'])->toBe(5.5)
        ->and($enriched['stock'])->toBe(10)
        ->and($enriched['backorder'])->toBeFalse()
        // number è l'indice + 1: la cassa numera i prodotti da 1
        ->and($enriched['number'])->toBe(3)
        ->and($enriched['total_in_cart'])->toBe(3)
        ->and($enriched['remaining_stock'])->toBe(7)
        ->and($enriched['is_out_of_stock'])->toBeFalse()
        ->and($enriched['has_insufficient_ingredients'])->toBeFalse()
        ->and($enriched['sold'])->toBe(42);
});

it('arricchisce un prodotto senza carrello con venduti a zero per default', function () {
    $product = Product::factory()->create(['stock' => 8]);

    $enriched = $this->enrichment->getEnrichedProduct([], $product, 0);

    expect($enriched['number'])->toBe(1)
        ->and($enriched['total_in_cart'])->toBe(0)
        ->and($enriched['remaining_stock'])->toBe(8)
        ->and($enriched['sold'])->toBe(0);
});

it('segnala nel prodotto arricchito lo sforamento di stock e ingredienti', function () {
    $product = Product::factory()->create(['stock' => 2]);
    $ingredient = Ingredient::factory()->create(['stock' => 1]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 1]);
    $product->load('ingredients');

    $enriched = $this->enrichment->getEnrichedProduct([enrichCartRow($product->id, 3)], $product, 0);

    expect($enriched['remaining_stock'])->toBe(-1)
        ->and($enriched['is_out_of_stock'])->toBeTrue()
        ->and($enriched['has_insufficient_ingredients'])->toBeTrue();
});

it('segnala nel prodotto arricchito la scorta bassa senza esaurito', function () {
    setConfig('low_stock_threshold', '3');
    $product = Product::factory()->create(['stock' => 5]);

    $enriched = $this->enrichment->getEnrichedProduct([enrichCartRow($product->id, 2)], $product, 0);

    expect($enriched['remaining_units'])->toBe(3)
        ->and($enriched['is_low_stock'])->toBeTrue()
        ->and($enriched['has_low_ingredients'])->toBeFalse()
        ->and($enriched['is_out_of_stock'])->toBeFalse();
});

it('attribuisce la scorta bassa del prodotto arricchito agli ingredienti', function () {
    setConfig('low_stock_threshold', '3');
    // Giacenza abbondante, ma l'ingrediente basta solo per altre 2 unità.
    $product = Product::factory()->create(['stock' => 100]);
    $ingredient = Ingredient::factory()->create(['stock' => 4]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 2]);
    $product->load('ingredients');

    $enriched = $this->enrichment->getEnrichedProduct([], $product, 0);

    expect($enriched['remaining_stock'])->toBe(100)
        ->and($enriched['remaining_units'])->toBe(2)
        ->and($enriched['is_low_stock'])->toBeTrue()
        ->and($enriched['has_low_ingredients'])->toBeTrue()
        ->and($enriched['is_out_of_stock'])->toBeFalse();
});

it('azzera le segnalazioni sul prodotto arricchito in backorder', function () {
    $product = Product::factory()->backorder()->create(['stock' => 0]);
    $ingredient = Ingredient::factory()->exhausted()->create();
    $product->ingredients()->attach($ingredient->id, ['qty' => 1]);
    $product->load('ingredients');

    $enriched = $this->enrichment->getEnrichedProduct([enrichCartRow($product->id, 5)], $product, 0);

    expect($enriched['backorder'])->toBeTrue()
        ->and($enriched['remaining_stock'])->toBe(-5)
        ->and($enriched['remaining_units'])->toBeNull()
        ->and($enriched['is_out_of_stock'])->toBeFalse()
        ->and($enriched['is_low_stock'])->toBeFalse()
        ->and($enriched['has_insufficient_ingredients'])->toBeFalse()
        ->and($enriched['has_low_ingredients'])->toBeFalse();
});

// ==================== getSoldSinceQueueReset ====================

it('somma i venduti di un prodotto presente su due code con reset diversi', function () {
    $product = Product::factory()->create();

    $codaA = Queue::factory()->resetAt(now()->subHours(2))->create();
    $codaB = Queue::factory()->resetAt(now()->subMinutes(30))->create();

    // dopo il reset della propria coda: contano
    vendita($product, $codaA, 4, now()->subHour());
    vendita($product, $codaB, 2, now()->subMinutes(10));
    // prima del reset della propria coda: non contano
    vendita($product, $codaA, 50, now()->subHours(3));
    vendita($product, $codaB, 70, now()->subHours(1));

    expect($this->enrichment->getSoldSinceQueueReset([$product->id]))->toBe([$product->id => 6]);
});

it('non conta gli ordini precedenti al reset della coda', function () {
    $product = Product::factory()->create();
    $coda = Queue::factory()->resetAt(now()->subHour())->create();

    vendita($product, $coda, 9, now()->subHours(5));

    expect($this->enrichment->getSoldSinceQueueReset([$product->id]))->toBe([]);
});

it('conta l ordine effettuato esattamente nell istante del reset', function () {
    $product = Product::factory()->create();
    $istante = now()->subHour()->startOfSecond();
    $coda = Queue::factory()->resetAt($istante)->create();

    vendita($product, $coda, 3, $istante);

    expect($this->enrichment->getSoldSinceQueueReset([$product->id]))->toBe([$product->id => 3]);
});

it('conta tutto lo storico per una coda mai azzerata', function () {
    $product = Product::factory()->create();
    $coda = Queue::factory()->create(['reset_at' => null]);

    vendita($product, $coda, 3, now()->subDays(10));
    vendita($product, $coda, 5, now());

    expect($this->enrichment->getSoldSinceQueueReset([$product->id]))->toBe([$product->id => 8]);
});

it('non conta le vendite di un ordine annullato', function () {
    $product = Product::factory()->create();
    $coda = Queue::factory()->create(['reset_at' => null]);

    vendita($product, $coda, 6, now()->subMinutes(5));
    $annullato = vendita($product, $coda, 100, now()->subMinutes(4))->order;
    $annullato->delete();

    expect($annullato->trashed())->toBeTrue()
        ->and($this->enrichment->getSoldSinceQueueReset([$product->id]))->toBe([$product->id => 6]);
});

it('non conta le righe di un ordine annullato le cui righe sono ancora attive', function () {
    $product = Product::factory()->create();
    $coda = Queue::factory()->create(['reset_at' => null]);

    $riga = vendita($product, $coda, 100, now()->subMinutes(4));
    // deleted_at sull'ordine senza propagare la cancellazione alle righe:
    // isola il filtro su orders.deleted_at
    $riga->order->forceFill(['deleted_at' => now()])->saveQuietly();

    expect(OrderItem::query()->whereKey($riga->id)->exists())->toBeTrue()
        ->and($this->enrichment->getSoldSinceQueueReset([$product->id]))->toBe([]);
});

it('non conta una riga d ordine cancellata', function () {
    $product = Product::factory()->create();
    $coda = Queue::factory()->create(['reset_at' => null]);
    $order = Order::factory()->for($coda)->create();

    OrderItem::factory()->of($product, 7)->for($order)->create();
    $cancellata = OrderItem::factory()->of($product, 100)->for($order)->create();
    $cancellata->delete();

    expect($this->enrichment->getSoldSinceQueueReset([$product->id]))->toBe([$product->id => 7]);
});

it('restituisce un array vuoto senza prodotti in ingresso', function () {
    $product = Product::factory()->create();
    vendita($product, Queue::factory()->create(), 5, now());

    expect($this->enrichment->getSoldSinceQueueReset([]))->toBe([]);
});

it('restituisce i venduti per prodotto senza contaminare gli altri prodotti', function () {
    $panino = Product::factory()->create();
    $piadina = Product::factory()->create();
    $mai = Product::factory()->create();
    $coda = Queue::factory()->create(['reset_at' => null]);

    vendita($panino, $coda, 3, now());
    vendita($panino, $coda, 4, now());
    vendita($piadina, $coda, 1, now());
    vendita($mai, $coda, 9, now());

    $sold = $this->enrichment->getSoldSinceQueueReset([$panino->id, $piadina->id]);

    expect($sold)->toBe([$panino->id => 7, $piadina->id => 1])
        ->and($sold)->not->toHaveKey($mai->id);
});

it('conta anche gli ordini senza coda', function () {
    $product = Product::factory()->create();
    $order = Order::factory()->withoutQueue()->create();
    OrderItem::factory()->of($product, 5)->for($order)->create();

    expect($order->queue_id)->toBeNull()
        ->and($this->enrichment->getSoldSinceQueueReset([$product->id]))->toBe([$product->id => 5]);
});

it('calcola i venduti con una sola query anche per molti prodotti', function () {
    $coda = Queue::factory()->create(['reset_at' => null]);
    $ids = [];

    foreach (range(1, 8) as $i) {
        $product = Product::factory()->create();
        vendita($product, $coda, $i, now());
        $ids[] = $product->id;
    }

    $sold = [];
    $queries = countQueries(function () use (&$sold, $ids) {
        $sold = $this->enrichment->getSoldSinceQueueReset($ids);
    });

    expect($queries)->toBe(1)->and($sold)->toHaveCount(8);
});

// ==================== getSortedEnrichedItems ====================

it('ordina gli item per sort_order e a parita per indice originale', function () {
    $primo = Product::factory()->create(['stock' => 100]);
    $secondo = Product::factory()->create(['stock' => 100]);
    $senzaNumero = Product::factory()->create(['stock' => 100]);

    $items = [
        enrichCartRow($primo->id, 1),       // indice 0, sort 3
        enrichCartRow($secondo->id, 1),     // indice 1, sort 1
        enrichCartRow($senzaNumero->id, 1), // indice 2, sort 999 (default)
        enrichCartRow($primo->id, 1),       // indice 3, sort 3
    ];

    $numeri = [$primo->id => 3, $secondo->id => 1];

    $sorted = $this->enrichment->getSortedEnrichedItems($items, $numeri);

    expect($sorted)->toHaveCount(4)
        ->and(array_column($sorted, 'original_index'))->toBe([1, 0, 3, 2])
        ->and(array_column($sorted, 'sort_order'))->toBe([1, 3, 3, 999])
        ->and(array_column($sorted, 'product_number'))->toBe([1, 3, 3, 0]);
});

it('mantiene l ordine di inserimento quando nessun prodotto ha un numero', function () {
    $a = Product::factory()->create(['stock' => 100]);
    $b = Product::factory()->create(['stock' => 100]);

    $items = [enrichCartRow($a->id), enrichCartRow($b->id), enrichCartRow($a->id)];

    $sorted = $this->enrichment->getSortedEnrichedItems($items, []);

    expect(array_column($sorted, 'original_index'))->toBe([0, 1, 2])
        ->and(array_column($sorted, 'sort_order'))->toBe([999, 999, 999]);
});

it('calcola totale riga e disponibilita su ogni item arricchito', function () {
    $product = Product::factory()->create(['price' => 4.25, 'stock' => 3]);

    $sorted = $this->enrichment->getSortedEnrichedItems([enrichCartRow($product->id, 2)], [$product->id => 1]);

    expect($sorted[0]['row_total'])->toBe(8.5)
        ->and($sorted[0]['remaining_stock'])->toBe(1)
        ->and($sorted[0]['is_out_of_stock'])->toBeFalse()
        ->and($sorted[0]['product']->id)->toBe($product->id)
        ->and($sorted[0]['item_id'])->toBe($sorted[0]['item']['item_id']);
});

it('gestisce gli item il cui prodotto non esiste piu', function () {
    $sorted = $this->enrichment->getSortedEnrichedItems([enrichCartRow(999999, 3)], []);

    expect($sorted[0]['product'])->toBeNull()
        ->and($sorted[0]['row_total'])->toBe(0)
        ->and($sorted[0]['remaining_stock'])->toBe(0)
        ->and($sorted[0]['is_out_of_stock'])->toBeFalse()
        ->and($sorted[0]['has_insufficient_ingredients'])->toBeFalse();
});

it('restituisce un array vuoto per un carrello vuoto', function () {
    expect($this->enrichment->getSortedEnrichedItems([], []))->toBe([]);
});
