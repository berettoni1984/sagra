<?php

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->prodotto = Product::factory()->create(['stock' => 20]);
});

function ordineDi(User $utente, Product $prodotto, int $qty = 2): Order
{
    $ordine = Order::factory()->create(['user_id' => $utente->id]);
    OrderItem::factory()->of($prodotto, $qty)->create(['order_id' => $ordine->id]);

    return $ordine;
}

it('una cassa non puo annullare l ordine di un altro operatore', function () {
    $altro = cassa();
    $altrui = ordineDi($altro, $this->prodotto);

    $mio = actingAsCassa();
    ordineDi($mio, $this->prodotto);

    // prima non c'era alcun controllo: chiunque poteva annullare ordini di altri
    expect(OrderResource::canDelete($altrui))->toBeFalse();
});

it('una cassa puo annullare il proprio ordine piu recente', function () {
    $mio = actingAsCassa();
    $vecchio = ordineDi($mio, $this->prodotto);
    $ultimo = ordineDi($mio, $this->prodotto);

    expect(OrderResource::canDelete($ultimo))->toBeTrue()
        ->and(OrderResource::canDelete($vecchio))->toBeFalse();
});

it('un admin puo annullare qualunque ordine', function () {
    $cassiere = cassa();
    $suo = ordineDi($cassiere, $this->prodotto);

    actingAsAdmin();

    expect(OrderResource::canDelete($suo))->toBeTrue();
});

it('la cancellazione definitiva e riservata agli admin', function () {
    $mio = actingAsCassa();
    $ordine = ordineDi($mio, $this->prodotto);

    expect(OrderResource::canForceDelete($ordine))->toBeFalse();

    actingAsAdmin();

    expect(OrderResource::canForceDelete($ordine->fresh()))->toBeTrue();
});

it('il pulsante di annullamento non compare sull ordine di un altro operatore', function () {
    $altro = cassa();
    $altrui = ordineDi($altro, $this->prodotto);

    actingAsCassa();

    Livewire::test(ViewOrder::class, ['record' => $altrui->getKey()])
        ->assertActionHidden('delete');
});

it('annullando un ordine da admin la giacenza rientra comunque', function () {
    $cassiere = cassa();
    $ordine = ordineDi($cassiere, $this->prodotto, 3);

    actingAsAdmin();

    Livewire::test(ViewOrder::class, ['record' => $ordine->getKey()])->callAction('delete');

    expect($this->prodotto->fresh()->stock)->toBe(23)
        ->and($ordine->fresh()->trashed())->toBeTrue();
});
