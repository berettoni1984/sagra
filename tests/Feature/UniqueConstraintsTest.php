<?php

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\RelationManagers\IngredientsRelationManager;
use App\Filament\Resources\QueueResource\Pages\CreateQueue;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Queue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** Estrae il codice di errore SQL da una QueryException. */
function codiceSql(Closure $callback): ?int
{
    try {
        $callback();
    } catch (QueryException $e) {
        return (int) ($e->errorInfo[1] ?? 0);
    }

    return null;
}

it('product_ingredient rifiuta lo stesso ingrediente due volte sullo stesso prodotto', function () {
    $prodotto = Product::factory()->create();
    $ingrediente = Ingredient::factory()->create();
    $prodotto->ingredients()->attach($ingrediente, ['qty' => 1]);

    // il doppio collegamento raddoppiava lo scarico dell'ingrediente a ogni vendita
    $codice = codiceSql(fn () => DB::table('product_ingredient')->insert([
        'product_id' => $prodotto->id,
        'ingredient_id' => $ingrediente->id,
        'qty' => 2,
    ]));

    expect($codice)->toBe(1062)
        ->and($prodotto->fresh()->ingredients)->toHaveCount(1);
});

it('lo stesso ingrediente resta collegabile a prodotti diversi', function () {
    $ingrediente = Ingredient::factory()->create();
    $primo = Product::factory()->create();
    $secondo = Product::factory()->create();

    $primo->ingredients()->attach($ingrediente, ['qty' => 1]);
    $secondo->ingredients()->attach($ingrediente, ['qty' => 3]);

    expect($ingrediente->fresh()->products)->toHaveCount(2);
});

it('products rifiuta due prodotti con lo stesso nome', function () {
    Product::factory()->create(['name' => 'PANINO UNICO']);

    $codice = codiceSql(fn () => DB::table('products')->insert([
        'name' => 'PANINO UNICO',
        'price' => '1.00',
    ]));

    expect($codice)->toBe(1062)
        ->and(Product::where('name', 'PANINO UNICO')->count())->toBe(1);
});

it('products.order e indicizzato per l ordinamento predefinito', function () {
    $indici = collect(DB::select('SHOW INDEX FROM products'))->pluck('Column_name');

    expect($indici)->toContain('order')->toContain('name');
});

it('il select di collegamento ingredienti esclude quelli gia collegati', function () {
    actingAsAdmin();

    $prodotto = Product::factory()->create();
    $collegato = Ingredient::factory()->create(['name' => 'PANE COLLEGATO']);
    $libero = Ingredient::factory()->create(['name' => 'PANE LIBERO']);
    $prodotto->ingredients()->attach($collegato, ['qty' => 1]);

    $componente = Livewire::test(IngredientsRelationManager::class, [
        'ownerRecord' => $prodotto,
        'pageClass' => EditProduct::class,
    ]);

    // lo schema personalizzato dell'AttachAction scavalcava il filtro di
    // Filament sui record già associati
    $opzioni = $componente->instance()->ingredientiCollegabili()->pluck('name')->all();

    expect($opzioni)->toContain('PANE LIBERO')
        ->and($opzioni)->not->toContain('PANE COLLEGATO');
});

it('la password richiede davvero una maiuscola', function () {
    $regex = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^\w\s]).*$/';

    // con il flag /i questa password passava, pur non avendo maiuscole
    expect((bool) preg_match($regex, 'abcdefg1!'))->toBeFalse()
        ->and((bool) preg_match($regex, 'Abcdefg1!'))->toBeTrue()
        // e resta obbligatoria anche una minuscola
        ->and((bool) preg_match($regex, 'ABCDEFG1!'))->toBeFalse();
});

it('order_number fuori range viene rifiutato dalla validazione del form', function (int $valore) {
    actingAsAdmin();

    Livewire::test(CreateQueue::class)
        ->fillForm(['comment' => 'test', 'order_number' => $valore])
        ->call('create')
        ->assertHasFormErrors(['order_number']);
})->with([
    'negativo' => -1,
    'oltre smallint' => 70000,
]);

it('order_number accetta i valori nel range consentito', function () {
    actingAsAdmin();

    Livewire::test(CreateQueue::class)
        ->fillForm(['comment' => 'valida', 'order_number' => 65535])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Queue::firstWhere('comment', 'valida')->order_number)->toBe(65535);
});

it('i loghi sono riservati agli admin', function () {
    actingAsCassa();
    $this->get('/logos')->assertForbidden();

    actingAsAdmin();
    $this->get('/logos')->assertSuccessful();
});
