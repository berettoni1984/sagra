<?php

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\RelationManagers\IngredientsRelationManager;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Queue;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    actingAsAdmin();
});

it('elenca i prodotti ordinati per la colonna order', function () {
    $terzo = Product::factory()->create(['order' => 3]);
    $primo = Product::factory()->create(['order' => 1]);
    $secondo = Product::factory()->create(['order' => 2]);

    Livewire::test(ListProducts::class)
        ->assertCanSeeTableRecords([$primo, $secondo, $terzo], inOrder: true);
});

it('somma le quantita ordinate nella colonna aggregata', function () {
    $prodotto = Product::factory()->create();
    $ordine = Order::factory()->create();
    OrderItem::factory()->of($prodotto, 4)->create(['order_id' => $ordine->id]);
    OrderItem::factory()->of($prodotto, 6)->create(['order_id' => $ordine->id]);

    // l'aggregato arriva da withSum('orderItems','quantity'), non da una join
    Livewire::test(ListProducts::class)
        ->assertCanSeeTableRecords([$prodotto])
        ->assertSee('10');
});

it('riordinando aggiorna la colonna order dei prodotti', function () {
    $a = Product::factory()->create(['order' => 1]);
    $b = Product::factory()->create(['order' => 2]);
    $c = Product::factory()->create(['order' => 3]);

    Livewire::test(ListProducts::class)->call('reorderTable', [$c->id, $a->id, $b->id]);

    expect($c->fresh()->order)->toBe(1)
        ->and($a->fresh()->order)->toBe(2)
        ->and($b->fresh()->order)->toBe(3);
});

it('la query di riordino non trascina join estranee sulla tabella products', function () {
    // La query base della tabella e' condivisa fra lettura e scrittura: quando
    // calcolava l'aggregato con leftJoin su order_items/orders, quelle join
    // finivano dentro la UPDATE del drag&drop.
    $a = Product::factory()->create(['order' => 1]);
    $b = Product::factory()->create(['order' => 2]);
    $ordine = Order::factory()->create();
    OrderItem::factory()->of($a, 2)->create(['order_id' => $ordine->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(ListProducts::class)->call('reorderTable', [$b->id, $a->id]);
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    $update = $queries->first(fn (string $q) => str_starts_with(strtolower(trim($q)), 'update `products`'));

    expect($update)->not->toBeNull()
        ->and(strtolower($update))->not->toContain('join')
        ->and(strtolower($update))->not->toContain('order_items')
        ->and($b->fresh()->order)->toBe(1);
});

it('crea un prodotto dal form assegnandolo alle code indicate', function () {
    $coda = Queue::factory()->create();

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'PANINO NUOVO',
            'price' => 4.5,
            'stock' => 25,
            'backorder' => false,
            'is_disabled' => false,
            'order' => 1,
            'queues' => [$coda->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $prodotto = Product::firstWhere('name', 'PANINO NUOVO');

    expect($prodotto)->not->toBeNull()
        ->and($prodotto->stock)->toBe(25)
        ->and($prodotto->queues->pluck('id')->all())->toBe([$coda->id]);
});

it('richiede nome prezzo e giacenza', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => null, 'price' => null, 'stock' => null])
        ->call('create')
        ->assertHasFormErrors(['name', 'price', 'stock']);
});

it('segnala il nome gia usato invece di far fallire il salvataggio', function () {
    // products.name ha un indice unico: senza la regola sul campo il create
    // moriva con l'errore SQL 1062 e la pagina restava in errore 500
    Product::factory()->create(['name' => 'Panino']);

    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => '  PANINO ', 'price' => 1, 'stock' => 0])
        ->call('create')
        ->assertHasFormErrors(['name']);

    expect(Product::count())->toBe(1);
});

it('permette di risalvare un prodotto senza cambiargli il nome', function () {
    // il controllo di unicita' deve escludere il record in modifica
    $prodotto = Product::factory()->create(['name' => 'Piadina', 'stock' => 1]);

    Livewire::test(EditProduct::class, ['record' => $prodotto->getKey()])
        ->fillForm(['name' => 'Piadina', 'stock' => 4])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($prodotto->fresh()->stock)->toBe(4);
});

it('modifica un prodotto e sincronizza le code', function () {
    $prodotto = Product::factory()->create(['stock' => 5]);
    $vecchia = Queue::factory()->create();
    $nuova = Queue::factory()->create();
    $prodotto->queues()->attach($vecchia);

    Livewire::test(EditProduct::class, ['record' => $prodotto->getKey()])
        ->fillForm(['stock' => 99, 'queues' => [$nuova->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($prodotto->fresh()->stock)->toBe(99)
        ->and($prodotto->fresh()->queues->pluck('id')->all())->toBe([$nuova->id]);
});

it('filtra i prodotti per coda', function () {
    $coda = Queue::factory()->create();
    $dentro = Product::factory()->create();
    $fuori = Product::factory()->create();
    $dentro->queues()->attach($coda);

    Livewire::test(ListProducts::class)
        ->filterTable('queue', ['queue' => [$coda->id]])
        ->assertCanSeeTableRecords([$dentro])
        ->assertCanNotSeeTableRecords([$fuori]);
});

it('filtra i prodotti per intervallo di data ordine', function () {
    $venduto = Product::factory()->create();
    $mai = Product::factory()->create();

    $ordine = Order::factory()->create();
    $ordine->forceFill(['created_at' => '2026-05-10 12:00:00'])->saveQuietly();
    OrderItem::factory()->of($venduto, 1)->create(['order_id' => $ordine->id]);

    Livewire::test(ListProducts::class)
        ->filterTable('created_at_range', [
            'created_from' => '2026-05-01 00:00:00',
            'created_until' => '2026-05-31 23:59:59',
        ])
        ->assertCanSeeTableRecords([$venduto])
        ->assertCanNotSeeTableRecords([$mai]);
});

it('il filtro per data non genera errori quando e vuoto', function () {
    $prodotto = Product::factory()->create();

    Livewire::test(ListProducts::class)
        ->filterTable('created_at_range', ['created_from' => null, 'created_until' => null])
        ->assertCanSeeTableRecords([$prodotto]);
});

it('assegna le code al prodotto in modo massivo', function () {
    $prodotti = Product::factory()->count(2)->create();
    $coda = Queue::factory()->create();

    Livewire::test(ListProducts::class)
        ->callTableBulkAction('queues', $prodotti, ['queues' => [$coda->id]]);

    foreach ($prodotti as $prodotto) {
        expect($prodotto->fresh()->queues->pluck('id')->all())->toBe([$coda->id]);
    }
});

it('mostra la quantita di ingrediente presa dalla pivot', function () {
    $prodotto = Product::factory()->create();
    $ingrediente = Ingredient::factory()->create(['name' => 'PANE']);
    $prodotto->ingredients()->attach($ingrediente, ['qty' => 7]);

    // la colonna e' 'pivot.qty': come 'qty' risultava sempre vuota, perche'
    // qty non esiste sulla tabella ingredients
    Livewire::test(IngredientsRelationManager::class, [
        'ownerRecord' => $prodotto,
        'pageClass' => EditProduct::class,
    ])
        ->assertCanSeeTableRecords([$ingrediente])
        ->assertSee('PANE')
        ->assertSee('7');
});

it('la giacenza dei prodotti backorder resta modificabile dalla tabella', function () {
    $prodotto = Product::factory()->backorder()->create(['stock' => 1]);

    Livewire::test(ListProducts::class)
        ->assertCanSeeTableRecords([$prodotto])
        ->assertTableColumnExists('stock');
});

it('espone il gate di riordino attivo', function () {
    expect(ProductResource::getPages())->toHaveKeys(['index', 'create', 'edit']);
});
