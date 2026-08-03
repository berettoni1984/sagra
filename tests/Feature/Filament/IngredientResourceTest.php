<?php

use App\Filament\Resources\IngredientResource\Pages\CreateIngredient;
use App\Filament\Resources\IngredientResource\Pages\EditIngredient;
use App\Filament\Resources\IngredientResource\Pages\ListIngredients;
use App\Models\Ingredient;
use Livewire\Livewire;

beforeEach(function () {
    actingAsAdmin();
});

it('elenca gli ingredienti', function () {
    $ingredienti = Ingredient::factory()->count(3)->create();

    Livewire::test(ListIngredients::class)->assertCanSeeTableRecords($ingredienti);
});

it('crea un ingrediente dal form', function () {
    Livewire::test(CreateIngredient::class)
        ->fillForm(['name' => 'POMODORO', 'stock' => 50])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Ingredient::firstWhere('name', 'POMODORO'))->not->toBeNull()
        ->and(Ingredient::firstWhere('name', 'POMODORO')->stock)->toBe(50);
});

it('salva il flag di disabilitazione impostato dal form in creazione', function () {
    // is_disabled non era in $fillable: il form mostrava successo ma il valore
    // veniva scartato, e l'ingrediente continuava a bloccare gli ordini
    Livewire::test(CreateIngredient::class)
        ->fillForm(['name' => 'CIPOLLA', 'stock' => 10, 'is_disabled' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Ingredient::firstWhere('name', 'CIPOLLA')->is_disabled)->toBeTrue();
});

it('salva il flag di disabilitazione impostato dal form in modifica', function () {
    $ingrediente = Ingredient::factory()->create(['is_disabled' => false]);

    Livewire::test(EditIngredient::class, ['record' => $ingrediente->getKey()])
        ->fillForm(['is_disabled' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($ingrediente->fresh()->is_disabled)->toBeTrue();
});

it('riabilita un ingrediente dal form', function () {
    $ingrediente = Ingredient::factory()->disabled()->create();

    Livewire::test(EditIngredient::class, ['record' => $ingrediente->getKey()])
        ->fillForm(['is_disabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($ingrediente->fresh()->is_disabled)->toBeFalse();
});

it('modifica la giacenza di un ingrediente', function () {
    $ingrediente = Ingredient::factory()->create(['stock' => 5]);

    Livewire::test(EditIngredient::class, ['record' => $ingrediente->getKey()])
        ->fillForm(['stock' => 77])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($ingrediente->fresh()->stock)->toBe(77);
});

it('richiede il nome dell ingrediente', function () {
    Livewire::test(CreateIngredient::class)
        ->fillForm(['name' => null, 'stock' => 1])
        ->call('create')
        ->assertHasFormErrors(['name']);
});

it('il flag di disabilitazione e mass assignable', function () {
    $ingrediente = new Ingredient(['name' => 'X', 'stock' => 1, 'is_disabled' => true]);

    expect($ingrediente->getAttributes())->toHaveKey('is_disabled')
        ->and($ingrediente->is_disabled)->toBeTrue();
});
