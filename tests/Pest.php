<?php

use App\Models\Config;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

// La memoizzazione dei Config è statica e sopravvive al rollback di
// RefreshDatabase: senza flush il valore scritto da un test resterebbe visibile
// a quelli successivi, che leggerebbero una configurazione non più nel database.
pest()->beforeEach(fn () => Config::flushValueCache())->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

/**
 * Utente con un ruolo, pronto per il pannello.
 *
 * I ruoli esistono già: li crea la migration seed_admin_and_cassa_roles, che
 * RefreshDatabase esegue insieme alle altre.
 */
function userWithRole(string $role, array $attributes = []): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create($attributes);
    $user->syncRoles([Role::findOrCreate($role, 'web')->name]);

    return $user->fresh();
}

function admin(array $attributes = []): User
{
    return userWithRole(User::ROLE_ADMIN, $attributes);
}

function cassa(array $attributes = []): User
{
    return userWithRole(User::ROLE_CASSA, $attributes);
}

function cameriere(array $attributes = []): User
{
    return userWithRole(User::ROLE_CAMERIERI, $attributes);
}

/**
 * Autentica un admin e lo restituisce.
 */
function actingAsAdmin(array $attributes = []): User
{
    $user = admin($attributes);
    test()->actingAs($user);

    return $user;
}

function actingAsCassa(array $attributes = []): User
{
    $user = cassa($attributes);
    test()->actingAs($user);

    return $user;
}

function actingAsCameriere(array $attributes = []): User
{
    $user = cameriere($attributes);
    test()->actingAs($user);

    return $user;
}

/**
 * Imposta un valore di configurazione svuotando la cache memoizzata.
 */
function setConfig(string $code, string $value): void
{
    Config::updateOrCreate(['code' => $code], ['config_value' => $value]);
    Config::flushValueCache();
}

/**
 * Conta le query eseguite dalla callback.
 */
function countQueries(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}
