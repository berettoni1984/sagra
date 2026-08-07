<?php

use App\Filament\Resources\LogoResource\Pages\ListLogos;
use App\Models\Logo;
use Livewire\Livewire;

beforeEach(function () {
    actingAsAdmin();
});

it('elenca i loghi ordinati per la colonna order', function () {
    $terzo = Logo::factory()->atOrder(3)->create();
    $primo = Logo::factory()->atOrder(1)->create();
    $secondo = Logo::factory()->atOrder(2)->create();

    Livewire::test(ListLogos::class)
        ->assertCanSeeTableRecords([$primo, $secondo, $terzo], inOrder: true);
});

it('riordinando aggiorna la colonna order dei loghi', function () {
    $a = Logo::factory()->atOrder(1)->create();
    $b = Logo::factory()->atOrder(2)->create();
    $c = Logo::factory()->atOrder(3)->create();

    Livewire::test(ListLogos::class)->call('reorderTable', [$c->id, $a->id, $b->id]);

    // il primo della lista è quello stampato sugli scontrini
    expect($c->fresh()->order)->toBe(1)
        ->and($a->fresh()->order)->toBe(2)
        ->and($b->fresh()->order)->toBe(3)
        ->and(Logo::defaultLogo()?->id)->toBe($c->id);
});

it('nega alla cassa l accesso ai loghi', function () {
    actingAsCassa();

    $this->get('/logos')->assertForbidden();
});
