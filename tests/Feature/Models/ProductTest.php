<?php

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Queue;
use Illuminate\Support\Facades\DB;

it('collega le code tramite la pivot product_queue', function () {
    $product = Product::factory()->create();
    $prima = Queue::factory()->create(['name' => 'Cucina']);
    $seconda = Queue::factory()->create(['name' => 'Bar']);

    $product->queues()->attach([$prima->id, $seconda->id]);

    expect($product->queues()->pluck('queues.id')->sort()->values()->all())
        ->toBe([$prima->id, $seconda->id])
        ->and(DB::table('product_queue')->where('product_id', $product->id)->count())->toBe(2)
        // la relazione è simmetrica: la stessa pivot serve Queue::products()
        ->and($prima->products()->pluck('products.id')->all())->toBe([$product->id]);
});

it('stacca una coda senza toccare le altre', function () {
    $product = Product::factory()->create();
    $cucina = Queue::factory()->create();
    $bar = Queue::factory()->create();
    $product->queues()->attach([$cucina->id, $bar->id]);

    $product->queues()->detach($cucina->id);

    expect($product->queues()->pluck('queues.id')->all())->toBe([$bar->id])
        ->and(DB::table('product_queue')->where('product_id', $product->id)->count())->toBe(1);
});

it('collega gli ingredienti conservando la quantita sulla pivot', function () {
    $product = Product::factory()->create();
    $pane = Ingredient::factory()->create(['name' => 'Pane']);
    $salsiccia = Ingredient::factory()->create(['name' => 'Salsiccia']);

    $product->ingredients()->attach($pane->id, ['qty' => 2]);
    $product->ingredients()->attach($salsiccia->id, ['qty' => 1]);

    $quantita = $product->ingredients()->get()
        ->mapWithKeys(fn (Ingredient $i) => [$i->name => (int) $i->pivot->qty])
        ->all();

    expect($quantita)->toBe(['Pane' => 2, 'Salsiccia' => 1]);
});

it('usa il valore di default 1 per la quantita di ingrediente non specificata', function () {
    $product = Product::factory()->create();
    $ingrediente = Ingredient::factory()->create();

    // qty è dichiarato ->default(1) sulla pivot product_ingredient
    DB::table('product_ingredient')->insert([
        'product_id' => $product->id,
        'ingredient_id' => $ingrediente->id,
    ]);

    expect((int) $product->ingredients()->first()->pivot->qty)->toBe(1);
});

it('espone le righe ordine collegate al prodotto', function () {
    $product = Product::factory()->create();
    $order = Order::factory()->create();
    OrderItem::factory()->of($product, 3)->create(['order_id' => $order->id]);
    OrderItem::factory()->of($product, 2)->create(['order_id' => $order->id]);
    // riga di un altro prodotto: non deve comparire
    OrderItem::factory()->create(['order_id' => $order->id]);

    expect($product->orderItems)->toHaveCount(2)
        ->and($product->orderItems()->sum('quantity'))->toEqual(5);
});

it('l accessor label restituisce il nome del prodotto', function () {
    $product = Product::factory()->create(['name' => 'Panino con salsiccia', 'price' => 4.50]);

    expect($product->label)->toBe('Panino con salsiccia')
        ->and($product->label)->not->toContain('4,50');
});

it('rende assegnabili in massa is_disabled backorder stock e order', function () {
    $product = Product::create([
        'name' => 'Patatine',
        'price' => 3,
        'stock' => 42,
        'backorder' => true,
        'is_disabled' => true,
        'order' => 7,
    ]);

    $salvato = Product::findOrFail($product->id);

    expect((int) $salvato->stock)->toBe(42)
        ->and((int) $salvato->backorder)->toBe(1)
        ->and((int) $salvato->is_disabled)->toBe(1)
        ->and((int) $salvato->order)->toBe(7)
        ->and($salvato->name)->toBe('Patatine');
});

it('non permette di assegnare in massa la chiave primaria', function () {
    $product = Product::factory()->create();

    $product->fill(['id' => 9999, 'name' => 'Rinominato']);

    expect($product->id)->not->toBe(9999)
        ->and($product->name)->toBe('Rinominato');
});

it('gli stati della factory riflettono la disponibilita del prodotto', function () {
    expect((int) Product::factory()->disabled()->create()->is_disabled)->toBe(1)
        ->and((int) Product::factory()->backorder()->create()->backorder)->toBe(1)
        ->and((int) Product::factory()->outOfStock()->create()->stock)->toBe(0)
        ->and((int) Product::factory()->outOfStock()->create()->backorder)->toBe(0);
});
