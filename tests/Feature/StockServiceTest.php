<?php

use App\Models\Ingredient;
use App\Models\Product;
use App\Services\StockService;

/*
|--------------------------------------------------------------------------
| StockService
|--------------------------------------------------------------------------
|
| Vive in Feature e non in Unit perché findProduct() fa una query
| (Product::with('ingredients')->find()): senza database non si può testare.
|
*/

beforeEach(function () {
    $this->stock = new StockService;
});

/**
 * Riga di carrello nella forma usata dalla cassa.
 *
 * @return array{item_id: string, product_id: int, quantity: int, note: string|null}
 */
function stockCartRow(int $productId, int $quantity = 1, ?string $note = null): array
{
    return [
        'item_id' => 'riga-'.$productId.'-'.$quantity.'-'.uniqid(),
        'product_id' => $productId,
        'quantity' => $quantity,
        'note' => $note,
    ];
}

// ==================== getRemainingStock ====================

it('sottrae dallo stock tutte le righe di carrello dello stesso prodotto', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $altro = Product::factory()->create(['stock' => 10]);

    $items = [
        stockCartRow($product->id, 3),
        stockCartRow($altro->id, 7),
        stockCartRow($product->id, 2),
    ];

    expect($this->stock->getRemainingStock($items, $product->id, $product->stock))->toBe(5)
        ->and($this->stock->getRemainingStock($items, $altro->id, $altro->stock))->toBe(3);
});

it('restituisce lo stock intero quando il prodotto non e nel carrello', function () {
    $product = Product::factory()->create(['stock' => 12]);

    expect($this->stock->getRemainingStock([], $product->id, $product->stock))->toBe(12);
});

it('restituisce un rimanente negativo quando il carrello supera lo stock', function () {
    $product = Product::factory()->create(['stock' => 2]);

    expect($this->stock->getRemainingStock([stockCartRow($product->id, 5)], $product->id, 2))->toBe(-3);
});

// ==================== isProductOutOfStock ====================

it('non considera mai fuori stock un prodotto in backorder', function () {
    $product = Product::factory()->backorder()->create(['stock' => 0]);

    $items = [stockCartRow($product->id, 999)];

    expect($this->stock->isProductOutOfStock($items, $product))->toBeFalse();
});

it('considera fuori stock un prodotto il cui rimanente scende sotto zero', function () {
    $product = Product::factory()->create(['stock' => 2]);

    expect($this->stock->isProductOutOfStock([stockCartRow($product->id, 3)], $product))->toBeTrue();
});

it('non considera fuori stock un prodotto il cui rimanente e esattamente zero', function () {
    $product = Product::factory()->create(['stock' => 3]);

    expect($this->stock->isProductOutOfStock([stockCartRow($product->id, 3)], $product))->toBeFalse();
});

it('considera fuori stock un prodotto con stock disponibile ma ingredienti insufficienti', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $ingredient = Ingredient::factory()->create(['stock' => 2]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 1]);
    $product->load('ingredients');

    $items = [stockCartRow($product->id, 3)];

    expect($this->stock->getRemainingStock($items, $product->id, $product->stock))->toBe(97)
        ->and($this->stock->isProductOutOfStock($items, $product))->toBeTrue();
});

// ==================== scorta bassa ====================

it('nasce con l avviso di scorta bassa disattivato', function () {
    // La migrazione crea low_stock_threshold a 0: chi non lo configura non deve
    // vedere nulla di nuovo in cassa.
    $product = Product::factory()->create(['stock' => 1]);

    expect($this->stock->getLowStockThreshold())->toBe(0)
        ->and($this->stock->isProductLowStock([], $product))->toBeFalse();
});

it('non avvisa di scorta bassa con una soglia non numerica o negativa', function (string $soglia) {
    setConfig('low_stock_threshold', $soglia);
    $product = Product::factory()->create(['stock' => 1]);

    expect($this->stock->getLowStockThreshold())->toBe(0)
        ->and($this->stock->isProductLowStock([], $product))->toBeFalse();
})->with(['', 'tre', '-5']);

it('avvisa quando il rimanente arriva alla soglia', function (int $inCarrello, bool $atteso) {
    setConfig('low_stock_threshold', '3');
    $product = Product::factory()->create(['stock' => 6]);

    expect($this->stock->isProductLowStock([stockCartRow($product->id, $inCarrello)], $product))->toBe($atteso);
})->with([
    // 6 - 2 = 4 pezzi: sopra soglia, nessun avviso
    [2, false],
    // 6 - 3 = 3 pezzi: soglia inclusa
    [3, true],
    [5, true],
    // 6 - 6 = 0 pezzi: ultimo pezzo venduto, ancora avviso e non alert
    [6, true],
]);

it('lascia vincere l alert di esaurito sull avviso di scorta bassa', function () {
    setConfig('low_stock_threshold', '3');
    $product = Product::factory()->create(['stock' => 2]);

    $items = [stockCartRow($product->id, 3)];

    expect($this->stock->isProductOutOfStock($items, $product))->toBeTrue()
        ->and($this->stock->isProductLowStock($items, $product))->toBeFalse();
});

it('non avvisa mai di scorta bassa un prodotto in backorder', function () {
    setConfig('low_stock_threshold', '3');
    $product = Product::factory()->backorder()->create(['stock' => 1]);
    $ingredient = Ingredient::factory()->create(['stock' => 1]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 1]);
    $product->load('ingredients');

    expect($this->stock->getRemainingUnits([], $product))->toBeNull()
        ->and($this->stock->isProductLowStock([], $product))->toBeFalse()
        ->and($this->stock->hasLowIngredients([], $product))->toBeFalse();
});

it('conta come rimanenti le unita ottenibili dagli ingredienti', function () {
    setConfig('low_stock_threshold', '3');
    // Giacenza abbondante ma un ingrediente da 7 pezzi con pivot qty 2: bastano
    // per 3 unità, non per 3,5.
    $product = Product::factory()->create(['stock' => 100]);
    $ingredient = Ingredient::factory()->create(['stock' => 7]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 2]);
    $product->load('ingredients');

    expect($this->stock->getRemainingUnits([], $product))->toBe(3)
        ->and($this->stock->isProductLowStock([], $product))->toBeTrue()
        ->and($this->stock->hasLowIngredients([], $product))->toBeTrue();
});

it('sottrae il consumo del carrello dalle unita ottenibili dagli ingredienti', function () {
    setConfig('low_stock_threshold', '2');
    $product = Product::factory()->create(['stock' => 100]);
    $ingredient = Ingredient::factory()->create(['stock' => 10]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 1]);
    $product->load('ingredients');

    // Fuori carrello ne restano 10: nessun avviso. Con 8 nel carrello ne restano
    // 2 e l'avviso scatta.
    expect($this->stock->isProductLowStock([], $product))->toBeFalse()
        ->and($this->stock->isProductLowStock([stockCartRow($product->id, 8)], $product))->toBeTrue();
});

it('ignora gli ingredienti disabilitati e quelli a qty zero nel calcolo del rimanente', function () {
    setConfig('low_stock_threshold', '3');
    $product = Product::factory()->create(['stock' => 50]);
    $disabilitato = Ingredient::factory()->create(['stock' => 1, 'is_disabled' => true]);
    $senzaConsumo = Ingredient::factory()->create(['stock' => 1]);
    $product->ingredients()->attach($disabilitato->id, ['qty' => 1]);
    $product->ingredients()->attach($senzaConsumo->id, ['qty' => 0]);
    $product->load('ingredients');

    expect($this->stock->getRemainingUnits([], $product))->toBe(50)
        ->and($this->stock->isProductLowStock([], $product))->toBeFalse()
        ->and($this->stock->hasLowIngredients([], $product))->toBeFalse();
});

it('attribuisce la scorta bassa al prodotto quando gli ingredienti abbondano', function () {
    setConfig('low_stock_threshold', '3');
    $product = Product::factory()->create(['stock' => 2]);
    $ingredient = Ingredient::factory()->create(['stock' => 500]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 1]);
    $product->load('ingredients');

    expect($this->stock->isProductLowStock([], $product))->toBeTrue()
        ->and($this->stock->hasLowIngredients([], $product))->toBeFalse();
});

it('segnala la scorta bassa del carrello solo con la soglia attiva', function () {
    $product = Product::factory()->create(['stock' => 3]);
    $items = [stockCartRow($product->id, 1)];

    expect($this->stock->hasLowStockItems($items))->toBeFalse();

    setConfig('low_stock_threshold', '3');

    expect($this->stock->hasLowStockItems($items))->toBeTrue();
});

it('non segnala la scorta bassa del carrello quando tutte le righe abbondano', function () {
    setConfig('low_stock_threshold', '2');
    $product = Product::factory()->create(['stock' => 20]);

    expect($this->stock->hasLowStockItems([stockCartRow($product->id, 3)]))->toBeFalse();
});

// ==================== hasInsufficientIngredients ====================

it('moltiplica il pivot qty per la quantita in carrello', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $ingredient = Ingredient::factory()->create(['stock' => 10]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 3]);
    $product->load('ingredients');

    // 3 unità * qty 3 = 9 usati su 10 disponibili
    expect($this->stock->hasInsufficientIngredients([stockCartRow($product->id, 3)], $product))->toBeFalse()
        ->and($this->stock->getTotalIngredientUsedInCart([stockCartRow($product->id, 3)], $ingredient->id))->toBe(9.0);

    // 4 unità * qty 3 = 12 usati su 10 disponibili
    expect($this->stock->hasInsufficientIngredients([stockCartRow($product->id, 4)], $product))->toBeTrue()
        ->and($this->stock->getTotalIngredientUsedInCart([stockCartRow($product->id, 4)], $ingredient->id))->toBe(12.0);
});

it('non manda in insufficienza quando il consumo pareggia esattamente la giacenza', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $ingredient = Ingredient::factory()->create(['stock' => 12]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 4]);
    $product->load('ingredients');

    expect($this->stock->hasInsufficientIngredients([stockCartRow($product->id, 3)], $product))->toBeFalse();
});

it('ignora gli ingredienti disabilitati anche se esauriti', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $disabilitato = Ingredient::factory()->disabled()->exhausted()->create();
    $product->ingredients()->attach($disabilitato->id, ['qty' => 5]);
    $product->load('ingredients');

    $items = [stockCartRow($product->id, 10)];

    expect($this->stock->hasInsufficientIngredients($items, $product))->toBeFalse()
        ->and($this->stock->getTotalIngredientUsedInCart($items, $disabilitato->id))->toBe(0.0)
        ->and($this->stock->isProductOutOfStock($items, $product))->toBeFalse();
});

it('somma il consumo di un ingrediente condiviso su tutti i prodotti del carrello', function () {
    $ingredient = Ingredient::factory()->create(['stock' => 10]);

    $panino = Product::factory()->create(['stock' => 100]);
    $piadina = Product::factory()->create(['stock' => 100]);
    $panino->ingredients()->attach($ingredient->id, ['qty' => 2]);
    $piadina->ingredients()->attach($ingredient->id, ['qty' => 3]);
    $panino->load('ingredients');
    $piadina->load('ingredients');

    // 2 panini (4) + 2 piadine (6) = 10 su 10: ancora dentro
    $entro = [stockCartRow($panino->id, 2), stockCartRow($piadina->id, 2)];

    expect($this->stock->getTotalIngredientUsedInCart($entro, $ingredient->id))->toBe(10.0)
        ->and($this->stock->hasInsufficientIngredients($entro, $panino))->toBeFalse()
        ->and($this->stock->hasInsufficientIngredients($entro, $piadina))->toBeFalse();

    // 2 panini (4) + 3 piadine (9) = 13 su 10: entrambi i prodotti risultano
    // insufficienti, anche il panino che da solo consumerebbe solo 4
    $oltre = [stockCartRow($panino->id, 2), stockCartRow($piadina->id, 3)];

    expect($this->stock->getTotalIngredientUsedInCart($oltre, $ingredient->id))->toBe(13.0)
        ->and($this->stock->hasInsufficientIngredients($oltre, $panino))->toBeTrue()
        ->and($this->stock->hasInsufficientIngredients($oltre, $piadina))->toBeTrue();
});

it('non segnala insufficienza per un prodotto senza ingredienti', function () {
    $product = Product::factory()->create(['stock' => 1]);

    expect($this->stock->hasInsufficientIngredients([stockCartRow($product->id, 50)], $product))->toBeFalse();
});

it('non segnala insufficienza di ingredienti per un prodotto in backorder', function () {
    $product = Product::factory()->backorder()->create(['stock' => 0]);
    $ingredient = Ingredient::factory()->exhausted()->create();
    $product->ingredients()->attach($ingredient->id, ['qty' => 1]);
    $product->load('ingredients');

    expect($this->stock->hasInsufficientIngredients([stockCartRow($product->id, 5)], $product))->toBeFalse();
});

it('somma solo l ingrediente richiesto ignorando gli altri', function () {
    $pane = Ingredient::factory()->create(['stock' => 100]);
    $salsiccia = Ingredient::factory()->create(['stock' => 100]);
    $altrove = Ingredient::factory()->create(['stock' => 100]);

    $product = Product::factory()->create(['stock' => 100]);
    $product->ingredients()->attach($pane->id, ['qty' => 1]);
    $product->ingredients()->attach($salsiccia->id, ['qty' => 2]);
    $product->load('ingredients');

    $items = [stockCartRow($product->id, 4)];

    expect($this->stock->getTotalIngredientUsedInCart($items, $pane->id))->toBe(4.0)
        ->and($this->stock->getTotalIngredientUsedInCart($items, $salsiccia->id))->toBe(8.0)
        ->and($this->stock->getTotalIngredientUsedInCart($items, $altrove->id))->toBe(0.0);
});

// ==================== hasOutOfStockItems ====================

it('segnala il carrello fuori stock se almeno una riga sfora', function () {
    $ok = Product::factory()->create(['stock' => 50]);
    $ko = Product::factory()->create(['stock' => 1]);

    expect($this->stock->hasOutOfStockItems([stockCartRow($ok->id, 2), stockCartRow($ko->id, 4)]))->toBeTrue();
});

it('non segnala il carrello fuori stock quando tutte le righe rientrano', function () {
    $a = Product::factory()->create(['stock' => 5]);
    $b = Product::factory()->create(['stock' => 5]);

    expect($this->stock->hasOutOfStockItems([stockCartRow($a->id, 5), stockCartRow($b->id, 1)]))->toBeFalse();
});

it('non conta le righe in backorder nel controllo del carrello', function () {
    $backorder = Product::factory()->backorder()->create(['stock' => 0]);
    $normale = Product::factory()->create(['stock' => 10]);

    $items = [stockCartRow($backorder->id, 500), stockCartRow($normale->id, 2)];

    expect($this->stock->hasOutOfStockItems($items))->toBeFalse();
});

it('segnala il carrello fuori stock per ingredienti insufficienti', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $ingredient = Ingredient::factory()->create(['stock' => 4]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 2]);

    expect($this->stock->hasOutOfStockItems([stockCartRow($product->id, 3)]))->toBeTrue();
});

it('considera un carrello vuoto sempre disponibile', function () {
    expect($this->stock->hasOutOfStockItems([]))->toBeFalse();
});

it('somma le righe separate dello stesso prodotto nel controllo del carrello', function () {
    $product = Product::factory()->create(['stock' => 3]);

    // due righe da 2: da sole rientrano, sommate sforano
    $items = [stockCartRow($product->id, 2), stockCartRow($product->id, 2)];

    expect($this->stock->hasOutOfStockItems($items))->toBeTrue();
});

// ==================== prodotti inesistenti ====================

it('ignora senza errori le righe con un prodotto inesistente', function () {
    $items = [stockCartRow(999999, 3)];

    expect($this->stock->hasOutOfStockItems($items))->toBeFalse()
        ->and($this->stock->getTotalIngredientUsedInCart($items, 123456))->toBe(0.0)
        ->and($this->stock->getRemainingStock($items, 999999, 0))->toBe(-3);
});

it('valuta le righe valide anche se il carrello contiene un prodotto inesistente', function () {
    $product = Product::factory()->create(['stock' => 1]);

    expect($this->stock->hasOutOfStockItems([stockCartRow(999999, 3), stockCartRow($product->id, 2)]))->toBeTrue();
});

// ==================== cache per-istanza ====================

it('non rifa una query per ogni riga di carrello grazie alla cache dei prodotti', function () {
    $product = Product::factory()->create(['stock' => 500]);
    $ingredient = Ingredient::factory()->create(['stock' => 500]);
    $product->ingredients()->attach($ingredient->id, ['qty' => 1]);

    $items = [];
    for ($i = 0; $i < 10; $i++) {
        $items[] = stockCartRow($product->id, 1);
    }

    $service = new StockService;

    // 10 righe con un solo prodotto distinto: la find() più l'eager load degli
    // ingredienti vengono eseguiti una volta sola, non una per riga
    $queries = countQueries(function () use ($service, $items) {
        $service->hasOutOfStockItems($items);
        $service->hasOutOfStockItems($items);
        $service->hasOutOfStockItems($items);
    });

    expect($queries)->toBe(2);
});

it('rifa le query su una nuova istanza del servizio', function () {
    $product = Product::factory()->create(['stock' => 500]);
    $items = [stockCartRow($product->id, 1)];

    $primo = countQueries(fn () => (new StockService)->hasOutOfStockItems($items));
    $secondo = countQueries(fn () => (new StockService)->hasOutOfStockItems($items));

    expect($primo)->toBe($secondo)->and($primo)->toBeGreaterThan(0);
});
