<?php

use App\Filament\Resources\ProductResource\Pages\ListProductsSold;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Queue;
use Livewire\Livewire;

/** La pagina somma per coda: gia' ordinato fino al numero servito, e il resto da preparare. */
it('divide le quantita fra ordini gia serviti e ordini ancora da preparare', function (): void {
    actingAsAdmin();

    $queue = Queue::factory()->create(['is_disabled' => false, 'order' => 1]);
    $product = Product::factory()->create(['is_disabled' => false]);
    $product->queues()->attach($queue);

    $servito = Order::factory()->create(['queue_id' => $queue->id, 'number' => 3]);
    OrderItem::factory()->of($product, 2)->create(['order_id' => $servito->id]);

    $daPreparare = Order::factory()->create(['queue_id' => $queue->id, 'number' => 9]);
    OrderItem::factory()->of($product, 5)->create(['order_id' => $daPreparare->id]);

    Livewire::test(ListProductsSold::class)
        ->set('tableFilters.number_now.queue_id', $queue->id)
        ->set('tableFilters.number_now.number_now', 5)
        ->assertOk()
        ->assertCanSeeTableRecords([$product])
        ->assertTableColumnStateSet('qty_done', 2, $product)
        ->assertTableColumnStateSet('qty_remaining', 5, $product);
});

/** Il conteggio e' per coda e parte dall'ultimo reset, come in cassa. */
it('ignora le altre code e gli ordini precedenti al reset', function (): void {
    actingAsAdmin();

    $queue = Queue::factory()->resetAt(now()->subHour())->create(['is_disabled' => false, 'order' => 1]);
    $altraCoda = Queue::factory()->create(['is_disabled' => false, 'order' => 2]);

    $product = Product::factory()->create(['is_disabled' => false]);
    $product->queues()->attach([$queue->id, $altraCoda->id]);

    $primaDelReset = Order::factory()->create([
        'queue_id' => $queue->id,
        'number' => 1,
        'created_at' => now()->subDay(),
    ]);
    OrderItem::factory()->of($product, 7)->create(['order_id' => $primaDelReset->id]);

    $altraFila = Order::factory()->create(['queue_id' => $altraCoda->id, 'number' => 1]);
    OrderItem::factory()->of($product, 4)->create(['order_id' => $altraFila->id]);

    $valido = Order::factory()->create(['queue_id' => $queue->id, 'number' => 2]);
    OrderItem::factory()->of($product, 3)->create(['order_id' => $valido->id]);

    Livewire::test(ListProductsSold::class)
        ->set('tableFilters.number_now.queue_id', $queue->id)
        ->set('tableFilters.number_now.number_now', 10)
        ->assertTableColumnStateSet('qty_done', 3, $product)
        ->assertTableColumnStateSet('qty_remaining', 0, $product);
});

/** I prodotti non assegnati alla coda scelta non compaiono nel foglio. */
it('elenca solo i prodotti assegnati alla coda scelta', function (): void {
    actingAsAdmin();

    $queue = Queue::factory()->create(['is_disabled' => false, 'order' => 1]);
    $altraCoda = Queue::factory()->create(['is_disabled' => false, 'order' => 2]);

    $suoProdotto = Product::factory()->create(['is_disabled' => false]);
    $suoProdotto->queues()->attach($queue);

    $altroProdotto = Product::factory()->create(['is_disabled' => false]);
    $altroProdotto->queues()->attach($altraCoda);

    Livewire::test(ListProductsSold::class)
        ->set('tableFilters.number_now.queue_id', $queue->id)
        ->assertCanSeeTableRecords([$suoProdotto])
        ->assertCanNotSeeTableRecords([$altroProdotto]);
});
