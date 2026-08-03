<?php

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->coda = Queue::factory()->create(['order_number' => 41]);
    $this->prodotto = Product::factory()->create(['price' => '3.00', 'stock' => 20]);
    // le opzioni del select prodotto sono filtrate per coda: senza
    // l'associazione il valore non supera la validazione del form
    $this->prodotto->queues()->attach($this->coda);
});

it('elenca gli ordini piu recenti per primi', function () {
    actingAsAdmin();
    $vecchio = Order::factory()->create(['queue_id' => $this->coda->id]);
    $vecchio->forceFill(['created_at' => '2026-01-01 10:00:00'])->saveQuietly();
    $nuovo = Order::factory()->create(['queue_id' => $this->coda->id]);
    $nuovo->forceFill(['created_at' => '2026-06-01 10:00:00'])->saveQuietly();

    Livewire::test(ListOrders::class)
        ->assertCanSeeTableRecords([$nuovo, $vecchio], inOrder: true);
});

it('permette di modificare solo il proprio ordine piu recente', function () {
    $mio = actingAsAdmin();
    $altro = admin();

    $mioVecchio = Order::factory()->create(['user_id' => $mio->id]);
    $mioUltimo = Order::factory()->create(['user_id' => $mio->id]);
    $altrui = Order::factory()->create(['user_id' => $altro->id]);

    expect(OrderResource::canEdit($mioUltimo))->toBeTrue()
        ->and(OrderResource::canEdit($mioVecchio))->toBeFalse()
        // l'ordine di un altro operatore non e' modificabile nemmeno se piu recente
        ->and(OrderResource::canEdit($altrui))->toBeFalse();
});

it('un ordine di un altra cassa creato dopo non blocca la modifica del proprio', function () {
    $mio = actingAsAdmin();
    $mioUltimo = Order::factory()->create(['user_id' => $mio->id]);

    // prima si confrontava col massimo id GLOBALE: bastava un'altra cassa per
    // impedire la correzione del proprio ordine appena emesso
    Order::factory()->create(['user_id' => admin()->id]);

    expect(OrderResource::canEdit($mioUltimo->fresh()))->toBeTrue();
});

it('non esegue un MAX(id) per ogni riga della tabella', function () {
    $utente = actingAsAdmin();
    Order::factory()->count(12)->create(['user_id' => $utente->id, 'queue_id' => $this->coda->id]);

    $reflection = new ReflectionClass(OrderResource::class);
    $reflection->getProperty('latestOrderIdCache')->setValue(null, []);

    $queries = countQueries(fn () => Livewire::test(ListOrders::class)->assertSuccessful());

    // canEdit() e' invocato due volte per riga (ViewAction + EditAction)
    expect($queries)->toBeLessThan(20);
});

it('mostra la pagina di dettaglio e di stampa', function () {
    actingAsAdmin();
    $ordine = Order::factory()->create(['queue_id' => $this->coda->id]);
    OrderItem::factory()->of($this->prodotto, 2)->create(['order_id' => $ordine->id]);

    $this->get('/orders/'.$ordine->id)->assertSuccessful();
    $this->get('/orders/'.$ordine->id.'/print')->assertSuccessful();
});

it('cancellando dalla pagina di dettaglio ripristina la giacenza', function () {
    $utente = actingAsAdmin();
    $ingrediente = Ingredient::factory()->create(['stock' => 100]);
    $this->prodotto->ingredients()->attach($ingrediente, ['qty' => 2]);

    $ordine = Order::factory()->create(['user_id' => $utente->id, 'queue_id' => $this->coda->id]);
    OrderItem::factory()->of($this->prodotto, 3)->create(['order_id' => $ordine->id]);

    Livewire::test(ViewOrder::class, ['record' => $ordine->getKey()])->callAction('delete');

    expect($this->prodotto->fresh()->stock)->toBe(23)      // 20 + 3
        ->and($ingrediente->fresh()->stock)->toBe(106)     // 100 + (3 x 2)
        ->and($ordine->fresh()->trashed())->toBeTrue();
});

it('la cancellazione multipla dall elenco ripristina la giacenza di tutti gli ordini', function () {
    actingAsAdmin();

    $ordini = collect(range(1, 3))->map(function () {
        $ordine = Order::factory()->create(['queue_id' => $this->coda->id]);
        OrderItem::factory()->of($this->prodotto, 2)->create(['order_id' => $ordine->id]);

        return $ordine;
    });

    Livewire::test(ListOrders::class)->callTableBulkAction('delete', $ordini);

    // 20 + (3 ordini x 2 pezzi): prima la merce non rientrava affatto
    expect($this->prodotto->fresh()->stock)->toBe(26);
});

it('creando un ordine incrementa il numero della coda e scarica la giacenza', function () {
    actingAsAdmin();

    Livewire::test(CreateOrder::class)
        ->fillForm([
            'queue_id' => $this->coda->id,
            'orderItems' => [
                ['product_id' => $this->prodotto->id, 'quantity' => 4, 'amount' => '3.00', 'row_amount' => '12.00'],
            ],
            'total_amount' => '12.00',
            'total_paid' => '12.00',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $ordine = Order::latest('id')->first();

    expect($ordine->number)->toBe(42)
        ->and($this->coda->fresh()->order_number)->toBe(42)
        ->and($this->prodotto->fresh()->stock)->toBe(16)
        ->and($ordine->orderItems->first()->name)->toBe($this->prodotto->name);
});

it('modificando le quantita corregge la giacenza del solo delta', function () {
    $utente = actingAsAdmin();
    $ordine = Order::factory()->create(['user_id' => $utente->id, 'queue_id' => $this->coda->id]);
    $riga = OrderItem::factory()->of($this->prodotto, 2)->create(['order_id' => $ordine->id]);

    // la giacenza riflette gia' la vendita di 2 pezzi
    $this->prodotto->decrement('stock', 2);
    expect($this->prodotto->fresh()->stock)->toBe(18);

    Livewire::test(EditOrder::class, ['record' => $ordine->getKey()])
        ->fillForm([
            'orderItems' => [
                ['id' => $riga->id, 'product_id' => $this->prodotto->id, 'quantity' => 5,
                    'amount' => '3.00', 'row_amount' => '15.00'],
            ],
            'total_amount' => '15.00',
            'total_paid' => '15.00',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // da 2 a 5 pezzi: scarico di altri 3
    expect($this->prodotto->fresh()->stock)->toBe(15);
});

it('riducendo le quantita restituisce la giacenza', function () {
    $utente = actingAsAdmin();
    $ordine = Order::factory()->create(['user_id' => $utente->id, 'queue_id' => $this->coda->id]);
    $riga = OrderItem::factory()->of($this->prodotto, 5)->create(['order_id' => $ordine->id]);

    $this->prodotto->decrement('stock', 5);
    expect($this->prodotto->fresh()->stock)->toBe(15);

    Livewire::test(EditOrder::class, ['record' => $ordine->getKey()])
        ->fillForm([
            'orderItems' => [
                ['id' => $riga->id, 'product_id' => $this->prodotto->id, 'quantity' => 1,
                    'amount' => '3.00', 'row_amount' => '3.00'],
            ],
            'total_amount' => '3.00',
            'total_paid' => '3.00',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // da 5 a 1 pezzo: rientrano 4
    expect($this->prodotto->fresh()->stock)->toBe(19);
});

it('mostra il codice operatore nella tabella senza una query per riga', function () {
    $utente = actingAsAdmin(['code' => 'C9']);
    Order::factory()->count(6)->create(['user_id' => $utente->id, 'queue_id' => $this->coda->id]);

    $queries = countQueries(function () {
        Livewire::test(ListOrders::class)->assertSee('C9');
    });

    // l'utente e' caricato in eager loading: era una select per riga
    expect($queries)->toBeLessThan(20);
});
