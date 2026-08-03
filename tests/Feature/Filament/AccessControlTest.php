<?php

use App\Filament\Resources\ConfigResource;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\UserResource;
use App\Models\Config;
use App\Models\Order;
use App\Models\Queue;
use App\Models\User;
use Livewire\Livewire;

/** Pagine riservate agli admin da UserResource/ConfigResource::canAccess(). */
function paginePrivate(): array
{
    return ['/users', '/configs'];
}

/** Pagine operative che anche la cassa deve poter aprire. */
function pagineOperative(): array
{
    return ['/orders', '/products', '/order-items', '/queues', '/ingredients'];
}

it('nega alla cassa le pagine di utenti e configurazioni', function () {
    actingAsCassa();

    foreach (paginePrivate() as $url) {
        $this->get($url)->assertForbidden();
    }
});

it('nega alla cassa anche le sottopagine di utenti e configurazioni', function () {
    actingAsCassa();
    $altro = admin();
    $config = Config::factory()->create();

    $this->get('/users/create')->assertForbidden();
    $this->get('/users/'.$altro->id.'/edit')->assertForbidden();
    $this->get('/configs/create')->assertForbidden();
    $this->get('/configs/'.$config->id.'/edit')->assertForbidden();
});

it('consente alla cassa tutte le pagine operative', function () {
    actingAsCassa();

    foreach (pagineOperative() as $url) {
        $this->get($url)->assertSuccessful();
    }
});

it('consente all admin ogni pagina del pannello', function () {
    actingAsAdmin();

    foreach ([...paginePrivate(), ...pagineOperative(), '/logos'] as $url) {
        $this->get($url)->assertSuccessful();
    }
});

it('tratta un utente senza alcun ruolo come una cassa', function () {
    $senzaRuoli = User::factory()->create();

    expect($senzaRuoli->isAdmin())->toBeFalse()
        ->and($senzaRuoli->roles)->toBeEmpty();

    $this->actingAs($senzaRuoli);

    $this->get('/users')->assertForbidden();
    $this->get('/configs')->assertForbidden();
    $this->get('/orders')->assertSuccessful();
    $this->get('/products')->assertSuccessful();
});

it('distingue i ruoli nei gate canAccess delle risorse riservate', function () {
    $this->actingAs(cassa());
    expect(UserResource::canAccess())->toBeFalse()
        ->and(ConfigResource::canAccess())->toBeFalse();

    $this->actingAs(admin());
    expect(UserResource::canAccess())->toBeTrue()
        ->and(ConfigResource::canAccess())->toBeTrue();
});

it('non considera admin chi ha solo il ruolo cassa', function () {
    $utente = cassa();

    expect($utente->hasRole(User::ROLE_CASSA))->toBeTrue()
        ->and($utente->hasRole(User::ROLE_ADMIN))->toBeFalse()
        ->and($utente->isAdmin())->toBeFalse();
});

it('mostra il pulsante di azzeramento numerazione solo all admin', function () {
    actingAsAdmin();

    Livewire::test(ListOrders::class)
        ->assertActionVisible('resetNumber')
        ->assertSee(__('filament.reset_number'));
});

it('nasconde il pulsante di azzeramento numerazione alla cassa', function () {
    actingAsCassa();

    Livewire::test(ListOrders::class)
        ->assertActionHidden('resetNumber')
        ->assertDontSee(__('filament.reset_number'));
});

it('azzera la numerazione di tutte le code se l azione la esegue un admin', function () {
    actingAsAdmin();
    $prima = Queue::factory()->create(['order_number' => 42]);
    $seconda = Queue::factory()->create(['order_number' => 7]);

    Livewire::test(ListOrders::class)->callAction('resetNumber');

    expect($prima->fresh()->order_number)->toBe(0)
        ->and($seconda->fresh()->order_number)->toBe(0)
        ->and($prima->fresh()->reset_at)->not->toBeNull()
        ->and($seconda->fresh()->reset_at)->not->toBeNull();
});

it('blocca lato server l azzeramento numerazione invocato da una cassa', function () {
    actingAsCassa();
    $coda = Queue::factory()->create(['order_number' => 42]);

    // L'azione è nascosta nell'interfaccia, ma la richiesta Livewire si può
    // forgiare a mano: si invocano i metodi grezzi (mountAction /
    // callMountedAction) come farebbe un client ostile.
    //
    // Filament rivaluta la visibilità lato server e rifiuta di eseguire
    // l'azione senza sollevare eccezioni, quindi non si arriva nemmeno
    // all'abort_unless della closure (che resta come seconda barriera).
    // Ciò che conta è che la numerazione NON venga toccata.
    Livewire::test(ListOrders::class)
        ->call('mountAction', 'resetNumber')
        ->call('callMountedAction');

    expect($coda->fresh()->order_number)->toBe(42)
        ->and($coda->fresh()->reset_at)->toBeNull();
});

it('redirige al login chi non e autenticato', function () {
    foreach ([...pagineOperative(), ...paginePrivate()] as $url) {
        $this->get($url)->assertRedirect('/login');
    }
});

it('protegge anche le pagine di dettaglio da utenti non autenticati', function () {
    $ordine = Order::factory()->create();

    $this->get('/orders/create')->assertRedirect('/login');
    $this->get('/orders/'.$ordine->id)->assertRedirect('/login');
    $this->get('/orders/'.$ordine->id.'/print')->assertRedirect('/login');
});
