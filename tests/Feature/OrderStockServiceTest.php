<?php

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderStockService;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function () {
    $this->orderStock = new OrderStockService;
});

/**
 * Giacenza riletta dal database, non dal modello in memoria.
 */
function stockDi(Product|Ingredient $model): int
{
    return (int) $model->newQuery()->whereKey($model->getKey())->value('stock');
}

/**
 * Ordine con una sola riga del prodotto indicato.
 */
function ordineCon(Product $product, int $quantity): Order
{
    $order = Order::factory()->create();
    OrderItem::factory()->of($product, $quantity)->for($order)->create();

    return $order;
}

// ==================== deduct / restore ====================

it('scarica prodotto e ingredienti moltiplicando il pivot qty', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $pane = Ingredient::factory()->create(['stock' => 50]);
    $salsa = Ingredient::factory()->create(['stock' => 20]);
    $product->ingredients()->attach($pane->id, ['qty' => 3]);
    $product->ingredients()->attach($salsa->id, ['qty' => 1]);

    $this->orderStock->deduct(ordineCon($product, 4));

    expect(stockDi($product))->toBe(96)
        ->and(stockDi($pane))->toBe(38)   // 50 - (4 * 3)
        ->and(stockDi($salsa))->toBe(16); // 20 - (4 * 1)
});

it('ripristina esattamente quanto scaricato', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $pane = Ingredient::factory()->create(['stock' => 50]);
    $product->ingredients()->attach($pane->id, ['qty' => 3]);

    $order = ordineCon($product, 7);

    $this->orderStock->deduct($order);

    expect(stockDi($product))->toBe(93)->and(stockDi($pane))->toBe(29);

    $this->orderStock->restore($order);

    expect(stockDi($product))->toBe(100)->and(stockDi($pane))->toBe(50);
});

it('ripristina piu di quanto scaricato se chiamato due volte', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $order = ordineCon($product, 5);

    $this->orderStock->deduct($order);
    $this->orderStock->restore($order);
    $this->orderStock->restore($order);

    // il servizio non è idempotente: applica il movimento ogni volta
    expect(stockDi($product))->toBe(105);
});

it('somma i movimenti quando lo scarico viene applicato due volte', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $pane = Ingredient::factory()->create(['stock' => 50]);
    $product->ingredients()->attach($pane->id, ['qty' => 3]);

    $order = ordineCon($product, 4);

    $this->orderStock->deduct($order);
    $this->orderStock->deduct($order);

    // le scritture passano dal database: nessun movimento perso per via del
    // valore memorizzato nel modello
    expect(stockDi($product))->toBe(92)
        ->and(stockDi($pane))->toBe(26);
});

it('somma le righe distinte dello stesso prodotto nello stesso ordine', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $pane = Ingredient::factory()->create(['stock' => 100]);
    $product->ingredients()->attach($pane->id, ['qty' => 2]);

    $order = Order::factory()->create();
    OrderItem::factory()->of($product, 3)->for($order)->create();
    OrderItem::factory()->of($product, 2)->for($order)->create();

    $this->orderStock->deduct($order);

    expect(stockDi($product))->toBe(95)
        ->and(stockDi($pane))->toBe(90); // (3 + 2) * 2
});

it('scarica ogni prodotto dell ordine con i propri ingredienti', function () {
    $panino = Product::factory()->create(['stock' => 30]);
    $piadina = Product::factory()->create(['stock' => 40]);
    $condiviso = Ingredient::factory()->create(['stock' => 100]);
    $soloPiadina = Ingredient::factory()->create(['stock' => 100]);

    $panino->ingredients()->attach($condiviso->id, ['qty' => 1]);
    $piadina->ingredients()->attach($condiviso->id, ['qty' => 2]);
    $piadina->ingredients()->attach($soloPiadina->id, ['qty' => 5]);

    $order = Order::factory()->create();
    OrderItem::factory()->of($panino, 3)->for($order)->create();
    OrderItem::factory()->of($piadina, 2)->for($order)->create();

    $this->orderStock->deduct($order);

    expect(stockDi($panino))->toBe(27)
        ->and(stockDi($piadina))->toBe(38)
        ->and(stockDi($condiviso))->toBe(93)   // 100 - (3*1) - (2*2)
        ->and(stockDi($soloPiadina))->toBe(90); // 100 - (2*5)
});

it('lascia la giacenza intatta su un ordine senza righe', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = Order::factory()->create();

    $this->orderStock->deduct($order);
    $this->orderStock->restore($order);

    expect(stockDi($product))->toBe(10);
});

// ==================== casi particolari ====================

it('ignora gli ingredienti disabilitati', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $attivo = Ingredient::factory()->create(['stock' => 50]);
    $disabilitato = Ingredient::factory()->disabled()->create(['stock' => 50]);
    $product->ingredients()->attach($attivo->id, ['qty' => 2]);
    $product->ingredients()->attach($disabilitato->id, ['qty' => 2]);

    $order = ordineCon($product, 5);

    $this->orderStock->deduct($order);

    expect(stockDi($attivo))->toBe(40)
        ->and(stockDi($disabilitato))->toBe(50);

    $this->orderStock->restore($order);

    expect(stockDi($attivo))->toBe(50)
        ->and(stockDi($disabilitato))->toBe(50);
});

it('non muove la giacenza di un ingrediente con pivot qty a zero', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $ingredient = Ingredient::factory()->create(['stock' => 50]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 0]);

    $this->orderStock->deduct(ordineCon($product, 6));

    expect(stockDi($product))->toBe(94)
        ->and(stockDi($ingredient))->toBe(50);
});

it('salta le righe il cui prodotto e stato cancellato', function () {
    $product = Product::factory()->create(['stock' => 20]);
    $ingredient = Ingredient::factory()->create(['stock' => 20]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 1]);

    $order = Order::factory()->create();
    OrderItem::factory()->withoutProduct()->for($order)->create(['quantity' => 99]);
    OrderItem::factory()->of($product, 2)->for($order)->create();

    $this->orderStock->deduct($order);

    expect(stockDi($product))->toBe(18)
        ->and(stockDi($ingredient))->toBe(18);
});

it('non tocca la giacenza di un ordine con sole righe orfane', function () {
    $product = Product::factory()->create(['stock' => 20]);

    $order = Order::factory()->create();
    OrderItem::factory()->withoutProduct()->for($order)->create(['quantity' => 5]);

    $this->orderStock->deduct($order);

    expect(stockDi($product))->toBe(20);
});

// ==================== restoreMany ====================

it('ripristina la giacenza di piu ordini in una volta', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $pane = Ingredient::factory()->create(['stock' => 100]);
    $product->ingredients()->attach($pane->id, ['qty' => 2]);

    $primo = ordineCon($product, 3);
    $secondo = ordineCon($product, 4);

    $this->orderStock->deduct($primo);
    $this->orderStock->deduct($secondo);

    expect(stockDi($product))->toBe(93)->and(stockDi($pane))->toBe(86);

    $this->orderStock->restoreMany(Order::query()->whereKey([$primo->id, $secondo->id])->get());

    expect(stockDi($product))->toBe(100)->and(stockDi($pane))->toBe(100);
});

it('non fa nulla su una collezione di ordini vuota', function () {
    $product = Product::factory()->create(['stock' => 15]);

    $this->orderStock->restoreMany(new Collection);

    expect(stockDi($product))->toBe(15);
});

// ==================== applyDelta ====================

it('applica un delta positivo alla giacenza', function () {
    $product = Product::factory()->create(['stock' => 10]);

    $this->orderStock->applyDelta($product, 7);

    expect(stockDi($product))->toBe(17)->and($product->stock)->toBe(17);
});

it('applica un delta negativo alla giacenza', function () {
    $ingredient = Ingredient::factory()->create(['stock' => 10]);

    $this->orderStock->applyDelta($ingredient, -4);

    expect(stockDi($ingredient))->toBe(6);
});

it('non esegue alcuna query con delta zero', function () {
    $product = Product::factory()->create(['stock' => 10]);

    $queries = countQueries(fn () => $this->orderStock->applyDelta($product, 0));

    expect($queries)->toBe(0)->and(stockDi($product))->toBe(10);
});

it('porta la giacenza sotto zero se il delta negativo eccede', function () {
    $product = Product::factory()->create(['stock' => 2]);

    $this->orderStock->applyDelta($product, -5);

    expect(stockDi($product))->toBe(-3);
});

it('somma il delta al valore aggiornato sul database e non a quello in memoria', function () {
    $product = Product::factory()->create(['stock' => 100]);

    // un'altra cassa ha già scaricato 20 pezzi dopo che il modello è stato letto
    Product::query()->whereKey($product->id)->update(['stock' => 80]);
    expect($product->stock)->toBe(100);

    $this->orderStock->applyDelta($product, -5);

    // 80 - 5, non 100 - 5: la sottrazione la fa il database
    expect(stockDi($product))->toBe(75);
});
