<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Queue;
use App\Models\User;

it('espone le righe dell ordine', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->count(3)->create(['order_id' => $order->id]);
    // riga di un altro ordine: non deve comparire
    OrderItem::factory()->create();

    expect($order->orderItems)->toHaveCount(3)
        ->and($order->orderItems->pluck('order_id')->unique()->all())->toBe([$order->id]);
});

it('appartiene a una coda e a un utente', function () {
    $queue = Queue::factory()->create(['name' => 'Cucina']);
    $user = User::factory()->create(['name' => 'Mario']);

    $order = Order::factory()->create(['queue_id' => $queue->id, 'user_id' => $user->id]);

    expect($order->queue)->toBeInstanceOf(Queue::class)
        ->and($order->queue->name)->toBe('Cucina')
        ->and($order->user)->toBeInstanceOf(User::class)
        ->and($order->user->name)->toBe('Mario');
});

it('ammette coda e utente nulli', function () {
    $order = Order::factory()->withoutQueue()->create(['user_id' => null]);

    expect($order->queue_id)->toBeNull()
        ->and($order->user_id)->toBeNull()
        ->and($order->queue)->toBeNull()
        ->and($order->user)->toBeNull();
});

it('l accessor number_queue unisce numero e nome coda', function () {
    $queue = Queue::factory()->create(['name' => 'Griglia']);
    $order = Order::factory()->create(['number' => 12, 'queue_id' => $queue->id]);

    expect($order->number_queue)->toBe('12 Griglia');
});

it('l accessor number_queue non va in errore con coda nulla', function () {
    $order = Order::factory()->withoutQueue()->create(['number' => 7]);

    // il null-safe ?-> lascia solo lo spazio di separazione
    expect($order->number_queue)->toBe('7 ');
});

it('getOrderItemsQty somma le quantita delle righe', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 2]);
    OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 5]);
    OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1]);

    expect($order->getOrderItemsQty())->toBe(8)->toBeInt();
});

it('getOrderItemsQty restituisce zero su un ordine senza righe', function () {
    expect(Order::factory()->create()->getOrderItemsQty())->toBe(0);
});

it('getOrderItemsQty ignora le righe soft-deleted', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 4]);
    $cancellata = OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 6]);

    $cancellata->delete();

    expect($order->getOrderItemsQty())->toBe(4);
});

it('usa il soft delete e resta leggibile con withTrashed', function () {
    $order = Order::factory()->create();

    $order->delete();

    expect(Order::find($order->id))->toBeNull()
        ->and(Order::withTrashed()->find($order->id))->not->toBeNull()
        ->and(Order::withTrashed()->find($order->id)->deleted_at)->not->toBeNull()
        ->and(Order::onlyTrashed()->count())->toBe(1);
});

it('cancellando un ordine cancella a cascata le sue righe', function () {
    $order = Order::factory()->create();
    $righe = OrderItem::factory()->count(2)->create(['order_id' => $order->id]);
    $altro = OrderItem::factory()->create();

    $order->delete();

    expect(OrderItem::whereIn('id', $righe->pluck('id'))->count())->toBe(0)
        ->and(OrderItem::withTrashed()->whereIn('id', $righe->pluck('id'))->count())->toBe(2)
        ->and(OrderItem::withTrashed()->whereIn('id', $righe->pluck('id'))->get()
            ->every(fn (OrderItem $i) => $i->deleted_at !== null))->toBeTrue()
        // le righe degli altri ordini non vengono toccate
        ->and(OrderItem::find($altro->id))->not->toBeNull();
});

it('il force delete rimuove ordine e righe senza errori sulla foreign key', function () {
    $order = Order::factory()->create();
    $righe = OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

    // prima produceva SQL 1451: il vincolo era RESTRICT e le righe restavano
    $order->forceDelete();

    expect(Order::withTrashed()->find($order->id))->toBeNull()
        // rimosse dal cascade del database, non dall'hook deleting
        ->and(OrderItem::withTrashed()->whereIn('id', $righe->pluck('id'))->count())->toBe(0);
});

it('il force delete di un ordine gia soft-deleted rimuove anche le righe soft-deleted', function () {
    $order = Order::factory()->create();
    $righe = OrderItem::factory()->count(2)->create(['order_id' => $order->id]);
    $order->delete();

    $order->forceDelete();

    expect(Order::withTrashed()->count())->toBe(0)
        ->and(OrderItem::withTrashed()->whereIn('id', $righe->pluck('id'))->count())->toBe(0);
});

it('BUG NOTO: ripristinando un ordine le sue righe restano soft-deleted', function () {
    $order = Order::factory()->create();
    $righe = OrderItem::factory()->count(2)->create(['order_id' => $order->id]);
    $order->delete();

    $order->restore();

    // Order::booted() ha solo l'hook deleting, nessun restoring: le righe
    // non risalgono. L'ordine ripristinato risulta vuoto e a totale
    // incoerente. Il test documenta il comportamento attuale.
    expect(Order::find($order->id))->not->toBeNull()
        ->and(Order::find($order->id)->deleted_at)->toBeNull()
        ->and($order->fresh()->orderItems)->toHaveCount(0)
        ->and($order->fresh()->getOrderItemsQty())->toBe(0)
        ->and(OrderItem::onlyTrashed()->whereIn('id', $righe->pluck('id'))->count())->toBe(2);
});

it('rende assegnabili in massa i campi dell ordine', function () {
    $queue = Queue::factory()->create();
    $user = User::factory()->create();

    $order = Order::create([
        'number' => 33,
        'total_amount' => 21.50,
        'total_paid' => 25,
        'note' => 'senza cipolla',
        'queue_id' => $queue->id,
        'user_id' => $user->id,
    ]);

    $salvato = Order::findOrFail($order->id);

    expect((int) $salvato->number)->toBe(33)
        ->and((float) $salvato->total_amount)->toBe(21.50)
        ->and((float) $salvato->total_paid)->toBe(25.0)
        ->and($salvato->note)->toBe('senza cipolla')
        ->and($salvato->queue_id)->toBe($queue->id)
        ->and($salvato->user_id)->toBe($user->id);
});

it('le righe storicizzano nome e prezzo indipendenti dal prodotto', function () {
    $product = Product::factory()->create(['name' => 'Polenta', 'price' => 5.00]);
    $order = Order::factory()->create();
    OrderItem::factory()->of($product, 2)->create(['order_id' => $order->id]);

    $product->update(['name' => 'Polenta e funghi', 'price' => 7.00]);

    $riga = $order->orderItems()->first();

    expect($riga->name)->toBe('Polenta')
        ->and((float) $riga->amount)->toBe(5.00)
        ->and((float) $riga->row_amount)->toBe(10.00);
});
