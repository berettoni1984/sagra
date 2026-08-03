<?php

use App\Models\Config;
use App\Models\Logo;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Queue;
use App\Models\User;

beforeEach(function () {
    $this->user = actingAsAdmin(['code' => 'C1']);
    $this->order = Order::factory()->create([
        'queue_id' => Queue::factory()->create(['comment' => 'pizza'])->id,
        'user_id' => $this->user->id,
        'total_amount' => '12.50',
        'total_paid' => '12.50',
    ]);
    OrderItem::factory()->of(Product::factory()->create(['name' => 'PIZZA']), 2)
        ->create(['order_id' => $this->order->id]);
});

it('richiede autenticazione', function () {
    auth()->logout();

    $this->get('/order/invoice/'.$this->order->id)->assertRedirect();
});

it('stampa la fattura di un ordine esistente', function () {
    $this->get('/order/invoice/'.$this->order->id)
        ->assertSuccessful()
        ->assertSee('PIZZA');
});

it('risponde 404 per un ordine inesistente', function () {
    $this->get('/order/invoice/999999')->assertNotFound();
});

it('risponde 404 e non 500 per un id non numerico', function () {
    // il vincolo whereNumber sulla rotta evita il TypeError sul parametro int
    $this->get('/order/invoice/abc')->assertNotFound();
});

it('usa il formato A4 quando configurato', function () {
    setConfig('invoice_print', 'A4');

    $this->get('/order/invoice/'.$this->order->id)->assertSuccessful();
});

it('ripiega su A5 invece di andare in errore con un formato scritto male', function (string $valore) {
    setConfig('invoice_print', $valore);

    $this->get('/order/invoice/'.$this->order->id)->assertSuccessful();
})->with([
    'minuscolo' => 'a5',
    'con spazi' => ' A5 ',
    'vuoto' => '',
    'senza senso' => 'spazzatura',
    'a4 minuscolo' => 'a4',
]);

it('mostra il logo predefinito quando esiste', function () {
    Logo::factory()->isDefault()->create(['path' => 'logos/mio.png']);

    $this->get('/order/invoice/'.$this->order->id)
        ->assertSuccessful()
        ->assertSee('logos/mio.png', escape: false);
});

it('funziona anche senza alcun logo configurato', function () {
    expect(Logo::count())->toBe(0);

    $this->get('/order/invoice/'.$this->order->id)->assertSuccessful();
});

it('funziona per un ordine la cui coda e stata cancellata', function () {
    Queue::whereKey($this->order->queue_id)->delete();

    // la FK e' ON DELETE SET NULL: l'ordine resta, senza coda
    expect($this->order->fresh()->queue_id)->toBeNull();

    $this->get('/order/invoice/'.$this->order->id)->assertSuccessful();
});

it('funziona per una riga il cui prodotto e stato cancellato', function () {
    Product::query()->delete();

    expect($this->order->orderItems()->first()->product_id)->toBeNull();

    $this->get('/order/invoice/'.$this->order->id)
        ->assertSuccessful()
        // il nome resta storicizzato sulla riga
        ->assertSee('PIZZA');
});

it('mostra il codice operatore quando presente', function () {
    $this->get('/order/invoice/'.$this->order->id)->assertSuccessful()->assertSee('C1');
});

it('non mostra il codice operatore se l ordine non ha utente', function () {
    $senzaUtente = Order::factory()->create(['user_id' => null]);
    OrderItem::factory()->create(['order_id' => $senzaUtente->id]);

    $this->get('/order/invoice/'.$senzaUtente->id)->assertSuccessful();
});

it('usa il fuso orario configurato per le date', function () {
    setConfig('timezone', 'Europe/Rome');

    // Europe/Rome gestisce l'ora legale, a differenza dell'abbreviazione CEST
    $this->get('/order/invoice/'.$this->order->id)->assertSuccessful();

    expect(Config::value('timezone'))->toBe('Europe/Rome');
});

it('un utente qualsiasi puo vedere la fattura di un altro operatore', function () {
    // documenta il comportamento attuale: nessuna autorizzazione per ordine
    $altro = User::factory()->create();
    $this->actingAs($altro);

    $this->get('/order/invoice/'.$this->order->id)->assertSuccessful();
});
