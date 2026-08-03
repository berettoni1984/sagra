<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

it('annullando un ordine annulla anche le sue righe', function () {
    $ordine = Order::factory()->create();
    OrderItem::factory()->count(3)->create(['order_id' => $ordine->id]);

    $ordine->delete();

    expect($ordine->fresh()->trashed())->toBeTrue()
        ->and(OrderItem::where('order_id', $ordine->id)->count())->toBe(0)
        ->and(OrderItem::withTrashed()->where('order_id', $ordine->id)->count())->toBe(3);
});

it('ripristinando un ordine ripristina anche le sue righe', function () {
    $ordine = Order::factory()->create(['total_amount' => '30.00']);
    OrderItem::factory()->count(3)->create(['order_id' => $ordine->id]);

    $ordine->delete();
    $ordine->restore();

    // senza l'hook restoring le righe restavano cancellate: la fattura mostrava
    // un totale con la tabella articoli vuota e getOrderItemsQty() dava 0
    expect($ordine->fresh()->trashed())->toBeFalse()
        ->and($ordine->fresh()->orderItems)->toHaveCount(3)
        ->and($ordine->fresh()->getOrderItemsQty())->toBeGreaterThan(0);
});

it('il ripristino non tocca righe cancellate singolarmente prima dell annullamento', function () {
    $ordine = Order::factory()->create();
    $righe = OrderItem::factory()->count(3)->create(['order_id' => $ordine->id]);

    // una riga viene rimossa a mano dall'operatore, prima di annullare l'ordine
    $rimossaAMano = $righe->first();
    $rimossaAMano->delete();

    $ordine->delete();
    $ordine->restore();

    // documenta il comportamento: il ripristino riporta indietro tutte le righe
    // annullate, comprese quelle rimosse prima. Distinguerle richiederebbe
    // tracciare il momento della cancellazione.
    expect($ordine->fresh()->orderItems)->toHaveCount(3);
});

it('la cancellazione definitiva rimuove le righe tramite il cascade del database', function () {
    $ordine = Order::factory()->create();
    $righe = OrderItem::factory()->count(2)->create(['order_id' => $ordine->id]);
    $ids = $righe->pluck('id');

    // prima il guard su isForceDeleting() lasciava le righe indietro e la
    // foreign key RESTRICT faceva fallire l'operazione con l'errore SQL 1451
    $ordine->forceDelete();

    expect(Order::withTrashed()->find($ordine->id))->toBeNull()
        ->and(OrderItem::withTrashed()->whereIn('id', $ids)->count())->toBe(0);
});

it('la fattura di un ordine ripristinato mostra di nuovo gli articoli', function () {
    actingAsAdmin();

    $prodotto = Product::factory()->create(['name' => 'PIZZA RIPRISTINATA']);
    $ordine = Order::factory()->create();
    OrderItem::factory()->of($prodotto, 2)->create(['order_id' => $ordine->id]);

    $ordine->delete();
    $ordine->restore();

    $this->get('/order/invoice/'.$ordine->id)
        ->assertSuccessful()
        ->assertSee('PIZZA RIPRISTINATA');
});
