<?php

use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

it('espone le costanti dei due ruoli previsti', function () {
    expect(User::ROLE_ADMIN)->toBe('admin')
        ->and(User::ROLE_CASSA)->toBe('cassa');
});

it('usa il trait HasRoles', function () {
    expect(class_uses_recursive(User::class))->toContain(HasRoles::class)
        ->and(method_exists(User::class, 'hasRole'))->toBeTrue();
});

it('i ruoli admin e cassa esistono a database dopo le migration', function () {
    expect(Role::where('name', User::ROLE_ADMIN)->where('guard_name', 'web')->exists())->toBeTrue()
        ->and(Role::where('name', User::ROLE_CASSA)->where('guard_name', 'web')->exists())->toBeTrue();
});

it('isAdmin e vero solo per un utente con ruolo admin', function () {
    expect(admin()->isAdmin())->toBeTrue()
        ->and(cassa()->isAdmin())->toBeFalse();
});

it('un utente senza ruoli non e admin', function () {
    $utente = User::factory()->create();

    expect($utente->roles)->toHaveCount(0)
        ->and($utente->isAdmin())->toBeFalse()
        ->and($utente->hasRole(User::ROLE_CASSA))->toBeFalse();
});

it('assegna il ruolo indicato e nessun altro', function () {
    $utente = userWithRole(User::ROLE_CASSA);

    expect($utente->roles->pluck('name')->all())->toBe([User::ROLE_CASSA])
        ->and($utente->hasRole(User::ROLE_CASSA))->toBeTrue()
        ->and($utente->hasRole(User::ROLE_ADMIN))->toBeFalse();
});

it('syncRoles sostituisce il ruolo precedente', function () {
    $utente = cassa();

    $utente->syncRoles([User::ROLE_ADMIN]);

    expect($utente->fresh()->roles->pluck('name')->all())->toBe([User::ROLE_ADMIN])
        ->and($utente->fresh()->isAdmin())->toBeTrue();
});

it('rimuovere il ruolo admin toglie il privilegio', function () {
    $utente = admin();

    $utente->removeRole(User::ROLE_ADMIN);

    expect($utente->fresh()->isAdmin())->toBeFalse();
});

it('canAccessPanel consente l accesso a qualsiasi utente', function () {
    $panel = Filament::getPanel('admin');

    expect(admin()->canAccessPanel($panel))->toBeTrue()
        ->and(cassa()->canAccessPanel($panel))->toBeTrue()
        // anche un utente senza alcun ruolo entra nel pannello:
        // l'autorizzazione è gestita risorsa per risorsa, non all'ingresso
        ->and(User::factory()->create()->canAccessPanel($panel))->toBeTrue();
});

it('un utente ha i propri ordini', function () {
    $utente = cassa();
    Order::factory()->count(2)->create(['user_id' => $utente->id]);
    Order::factory()->create();

    expect($utente->orders)->toHaveCount(2)
        ->and($utente->orders->pluck('user_id')->unique()->all())->toBe([$utente->id]);
});

it('la password viene sempre hashata e mai esposta in serializzazione', function () {
    $utente = User::factory()->create(['password' => 'segreto123']);

    expect($utente->password)->not->toBe('segreto123')
        ->and(Hash::check('segreto123', $utente->password))->toBeTrue()
        ->and($utente->toArray())->not->toHaveKey('password')
        ->and($utente->toArray())->not->toHaveKey('remember_token');
});
