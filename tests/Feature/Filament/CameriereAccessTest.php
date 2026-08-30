<?php

use App\Filament\Resources\OrderResource;
use App\Models\Config;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Role;

/** Le due sole pagine di sala. */
it('consente al cameriere solo elenco ordini e venduto per coda', function (): void {
    actingAsCameriere();

    $this->get('/orders')->assertSuccessful();
    $this->get('/orders/'.Order::factory()->create()->id)->assertSuccessful();
    $this->get('/products/sold')->assertSuccessful();

    // Dopo il login il pannello manda alla prima voce di menu visibile: per il
    // cameriere e' l'elenco ordini, non una pagina che gli risponderebbe 403.
    $this->get('/')->assertRedirect('/orders');
});

/** Il dettaglio si legge, ma da li' non si modifica ne' si annulla nulla. */
it('non da al cameriere azioni di scrittura sugli ordini', function (): void {
    actingAsCameriere();
    // Gli ordini sono di chi li ha battuti in cassa: un cameriere non ne emette.
    $order = Order::factory()->create();

    expect(OrderResource::canEdit($order))->toBeFalse()
        ->and(OrderResource::canDelete($order))->toBeFalse()
        ->and(OrderResource::canCreate())->toBeFalse();
});

/** Nel menu di sala restano le due voci e sparisce tutto il resto. */
it('mostra al cameriere solo le voci di menu che puo aprire', function (): void {
    actingAsCameriere();

    $this->get('/orders')
        ->assertSee(__('filament.products_sold'))
        ->assertSee(__('filament.order_label_plural'))
        ->assertDontSee(__('filament.new_order'))
        ->assertDontSee(__('filament.product_label_plural'))
        ->assertDontSee(__('filament.queue_label_plural'));
});

it('nega al cameriere ogni altra pagina del pannello', function (): void {
    actingAsCameriere();

    $order = Order::factory()->create();
    $product = Product::factory()->create();
    $config = Config::factory()->create();

    $pagine = [
        '/products',
        '/products/create',
        '/products/'.$product->id.'/edit',
        '/orders/quick-create',
        '/orders/'.$order->id.'/edit',
        '/orders/'.$order->id.'/print',
        '/order-items',
        '/queues',
        '/ingredients',
        '/logos',
        '/configs',
        '/configs/'.$config->id.'/edit',
        '/users',
    ];

    foreach ($pagine as $url) {
        $this->get($url)->assertForbidden();
    }
});

/** Il venduto per coda lo aprono tutti i ruoli operativi, non solo la sala. */
it('lascia il venduto per coda anche a cassa e admin', function (): void {
    actingAsCassa();
    $this->get('/products/sold')->assertSuccessful();

    actingAsAdmin();
    $this->get('/products/sold')->assertSuccessful();
});

it('lascia intatto il pannello agli altri ruoli', function (): void {
    actingAsCassa();

    foreach (['/orders', '/products', '/order-items', '/queues', '/ingredients'] as $url) {
        $this->get($url)->assertSuccessful();
    }
});

/** Un admin che abbia anche il ruolo di sala resta admin. */
it('non chiude fuori un admin con anche il ruolo camerieri', function (): void {
    $utente = admin();
    $utente->assignRole(User::ROLE_CAMERIERI);

    expect($utente->fresh()->isCameriere())->toBeFalse();

    $this->actingAs($utente->fresh());
    $this->get('/users')->assertSuccessful();
    $this->get('/products/sold')->assertSuccessful();
});

it('crea il ruolo camerieri con la migration', function (): void {
    expect(User::ROLE_CAMERIERI)->toBe('camerieri')
        ->and(Role::where('name', User::ROLE_CAMERIERI)->where('guard_name', 'web')->exists())->toBeTrue();
});
