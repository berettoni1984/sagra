<?php

use App\Models\Config;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Queue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esegue la callback e restituisce il codice di errore MySQL, 0 se non fallisce.
 *
 * 1062 = duplicate entry, 1451/1452 = violazione di foreign key, 1364 = campo
 * senza default.
 */
function codiceErroreSql(Closure $callback): int
{
    try {
        $callback();
    } catch (QueryException $e) {
        return (int) ($e->errorInfo[1] ?? -1);
    }

    return 0;
}

it('product_queue rifiuta la stessa coppia prodotto-coda due volte', function () {
    $product = Product::factory()->create();
    $queue = Queue::factory()->create();
    $coppia = ['product_id' => $product->id, 'queue_id' => $queue->id];

    DB::table('product_queue')->insert($coppia);

    // il duplicato mostrava lo stesso prodotto due volte nel POS e raddoppiava
    // lo scarico degli ingredienti
    expect(codiceErroreSql(fn () => DB::table('product_queue')->insert($coppia)))->toBe(1062)
        ->and(DB::table('product_queue')->where($coppia)->count())->toBe(1);
});

it('product_queue accetta lo stesso prodotto su code diverse', function () {
    $product = Product::factory()->create();
    $cucina = Queue::factory()->create();
    $bar = Queue::factory()->create();

    DB::table('product_queue')->insert([
        ['product_id' => $product->id, 'queue_id' => $cucina->id],
        ['product_id' => $product->id, 'queue_id' => $bar->id],
    ]);

    expect(DB::table('product_queue')->where('product_id', $product->id)->count())->toBe(2);
});

it('product_queue rifiuta un prodotto inesistente', function () {
    $queue = Queue::factory()->create();

    $codice = codiceErroreSql(fn () => DB::table('product_queue')->insert([
        'product_id' => 999999,
        'queue_id' => $queue->id,
    ]));

    expect($codice)->toBe(1452);
});

it('product_queue rifiuta una coda inesistente', function () {
    $product = Product::factory()->create();

    $codice = codiceErroreSql(fn () => DB::table('product_queue')->insert([
        'product_id' => $product->id,
        'queue_id' => 999999,
    ]));

    expect($codice)->toBe(1452);
});

it('cancellare un prodotto elimina a cascata le sue righe in product_queue', function () {
    $product = Product::factory()->create();
    $altro = Product::factory()->create();
    $queue = Queue::factory()->create();
    $product->queues()->attach($queue->id);
    $altro->queues()->attach($queue->id);

    $product->delete();

    expect(DB::table('product_queue')->where('product_id', $product->id)->count())->toBe(0)
        // le pivot degli altri prodotti restano
        ->and(DB::table('product_queue')->where('product_id', $altro->id)->count())->toBe(1);
});

it('cancellare una coda elimina a cascata le sue righe in product_queue', function () {
    $product = Product::factory()->create();
    $queue = Queue::factory()->create();
    $altra = Queue::factory()->create();
    $product->queues()->attach([$queue->id, $altra->id]);

    $queue->delete();

    expect(DB::table('product_queue')->where('queue_id', $queue->id)->count())->toBe(0)
        ->and(DB::table('product_queue')->where('queue_id', $altra->id)->count())->toBe(1)
        // il prodotto sopravvive alla cancellazione della coda
        ->and(Product::find($product->id))->not->toBeNull();
});

it('configs.code non ammette codici duplicati', function () {
    Config::factory()->of('codice_duplicato_test', '12')->create();

    $codice = codiceErroreSql(fn () => DB::table('configs')->insert([
        'code' => 'codice_duplicato_test',
        'config_value' => '30',
    ]));

    expect($codice)->toBe(1062)
        ->and(DB::table('configs')->where('code', 'codice_duplicato_test')->count())->toBe(1);
});

it('products.order ha default zero e non serve indicarlo in insert', function () {
    // senza default questo insert finiva in SQL 1364 (field has no default value)
    $codice = codiceErroreSql(fn () => DB::table('products')->insert([
        'name' => 'Prodotto senza order',
        'price' => 3.50,
    ]));

    $riga = DB::table('products')->where('name', 'Prodotto senza order')->first();

    expect($codice)->toBe(0)
        ->and($riga)->not->toBeNull()
        ->and((int) $riga->order)->toBe(0)
        // gli altri campi con default seguono la stessa logica
        ->and((int) $riga->stock)->toBe(0)
        ->and((int) $riga->backorder)->toBe(0)
        ->and((int) $riga->is_disabled)->toBe(0);
});

it('cancellare una coda azzera queue_id sugli ordini senza cancellarli', function () {
    $queue = Queue::factory()->create();
    $order = Order::factory()->create(['queue_id' => $queue->id, 'number' => 42]);
    $righe = OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

    // prima era RESTRICT: la cancellazione finiva in SQL 1451 e pagina 500
    $codice = codiceErroreSql(fn () => $queue->delete());

    $ricaricato = Order::find($order->id);

    expect($codice)->toBe(0)
        ->and($ricaricato)->not->toBeNull()
        ->and($ricaricato->queue_id)->toBeNull()
        ->and((int) $ricaricato->number)->toBe(42)
        // le righe dell'ordine non vengono toccate
        ->and(OrderItem::whereIn('id', $righe->pluck('id'))->count())->toBe(2);
});

it('cancellare un prodotto azzera product_id conservando lo storico contabile', function () {
    $product = Product::factory()->create(['name' => 'Polenta', 'price' => 5.00]);
    $order = Order::factory()->create();
    $riga = OrderItem::factory()->of($product, 3)->create(['order_id' => $order->id]);

    $codice = codiceErroreSql(fn () => $product->delete());

    $ricaricata = OrderItem::find($riga->id);

    expect($codice)->toBe(0)
        ->and($ricaricata)->not->toBeNull()
        ->and($ricaricata->product_id)->toBeNull()
        ->and($ricaricata->product)->toBeNull()
        // nome e importi sono storicizzati sulla riga: restano leggibili
        ->and($ricaricata->name)->toBe('Polenta')
        ->and((float) $ricaricata->amount)->toBe(5.00)
        ->and((float) $ricaricata->row_amount)->toBe(15.00)
        ->and((int) $ricaricata->quantity)->toBe(3);
});

it('order_items.order_id cancella a cascata dal database', function () {
    $order = Order::factory()->create();
    $righe = OrderItem::factory()->count(2)->create(['order_id' => $order->id]);
    $superstite = OrderItem::factory()->create();

    // cancellazione fisica bypassando gli hook del model
    DB::table('orders')->where('id', $order->id)->delete();

    expect(OrderItem::withTrashed()->whereIn('id', $righe->pluck('id'))->count())->toBe(0)
        ->and(OrderItem::withTrashed()->find($superstite->id))->not->toBeNull();
});

it('order_items rifiuta un ordine inesistente', function () {
    $codice = codiceErroreSql(fn () => DB::table('order_items')->insert([
        'order_id' => 999999,
        'product_id' => null,
        'name' => 'orfana',
        'quantity' => 1,
        'amount' => 1,
        'row_amount' => 1,
    ]));

    expect($codice)->toBe(1452);
});

it('cancellare un utente azzera user_id sugli ordini', function () {
    $utente = cassa();
    $order = Order::factory()->create(['user_id' => $utente->id]);

    $utente->delete();

    expect(Order::find($order->id))->not->toBeNull()
        ->and(Order::find($order->id)->user_id)->toBeNull();
});

it('le regole ON DELETE dichiarate sono quelle attese', function () {
    $regole = fn (string $tabella) => collect(Schema::getForeignKeys($tabella))
        ->mapWithKeys(fn (array $fk) => [implode(',', $fk['columns']) => strtolower((string) $fk['on_delete'])])
        ->all();

    // criterio: colonna NOT NULL -> cascade, colonna nullable -> set null
    expect($regole('order_items'))->toBe([
        'order_id' => 'cascade',
        'product_id' => 'set null',
    ])
        ->and($regole('orders'))->toBe([
            'queue_id' => 'set null',
            'user_id' => 'set null',
        ])
        ->and($regole('product_queue'))->toBe([
            'product_id' => 'cascade',
            'queue_id' => 'cascade',
        ]);
});

it('product_queue ha un indice unico sulla coppia prodotto-coda', function () {
    $unici = collect(Schema::getIndexes('product_queue'))
        ->filter(fn (array $index) => $index['unique'])
        ->map(fn (array $index) => $index['columns'])
        ->values()
        ->all();

    expect($unici)->toBe([['product_id', 'queue_id']]);
});

it('configs ha un indice unico sul codice', function () {
    $unici = collect(Schema::getIndexes('configs'))
        ->filter(fn (array $index) => $index['unique'] && ! $index['primary'])
        ->map(fn (array $index) => $index['columns'])
        ->values()
        ->all();

    expect($unici)->toBe([['code']]);
});
