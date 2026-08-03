<?php

use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Enums\Status;
use Spatie\Health\Facades\Health;
use Spatie\SecurityAdvisoriesHealthCheck\SecurityAdvisoriesCheck;

/** @return array<int, string> */
function nomiControlli(): array
{
    return collect(Health::registeredChecks())
        ->map(fn ($check) => $check::class)
        ->all();
}

it('registra i controlli essenziali per la cassa', function () {
    // se database, redis o horizon si fermano, la cassa si blocca
    expect(nomiControlli())->toContain(
        DatabaseCheck::class,
        RedisCheck::class,
        HorizonCheck::class,
        ScheduleCheck::class,
        SecurityAdvisoriesCheck::class,
    );
});

it('non registra i controlli di igiene produzione fuori dalla produzione', function () {
    // in locale fallirebbero per definizione: debug attivo, ambiente non di
    // produzione, cache di config e rotte non generate
    expect(app()->isProduction())->toBeFalse()
        ->and(nomiControlli())->not->toContain(DebugModeCheck::class)
        ->and(nomiControlli())->not->toContain(EnvironmentCheck::class)
        ->and(nomiControlli())->not->toContain(OptimizedAppCheck::class);
});

it('il controllo del database passa', function () {
    $risultato = DatabaseCheck::new()->run();

    expect($risultato->status->value)->toBe(Status::ok()->value);
});

it('la pagina di stato e riservata agli admin', function () {
    actingAsCassa();

    $this->get('/health')->assertForbidden();
    $this->get('/health/json')->assertForbidden();
});

it('un admin vede la pagina di stato', function () {
    actingAsAdmin();

    // i risultati arrivano dallo store: senza esecuzioni precedenti la pagina
    // deve comunque rispondere
    $this->artisan('health:check')->assertSuccessful();

    $this->get('/health')->assertSuccessful();
    $this->get('/health/json')->assertSuccessful();
});

it('la pagina di stato non e accessibile senza autenticazione', function () {
    $this->get('/health')->assertRedirect('/login');
    $this->get('/health/json')->assertRedirect('/login');
});

it('il json di stato elenca i controlli eseguiti', function () {
    actingAsAdmin();
    $this->artisan('health:check')->assertSuccessful();

    $payload = $this->get('/health/json')->assertSuccessful()->json();

    expect($payload)->toBeArray()->not->toBeEmpty();
});

it('lo store salva lo storico dei risultati', function () {
    $this->artisan('health:check')->assertSuccessful();

    $this->assertDatabaseCount('health_check_result_history_items', count(nomiControlli()));
});

it('il comando di battito dello scheduler aggiorna il proprio segnale', function () {
    $this->artisan('health:schedule-check-heartbeat')->assertSuccessful();

    // con il battito registrato il controllo dello scheduler passa
    expect(ScheduleCheck::new()->run()->status->value)->toBe(Status::ok()->value);
});

it('redis punta all host del container e non a localhost', function () {
    // con 127.0.0.1 l'app non raggiungeva Redis e Horizon non poteva partire
    expect(config('database.redis.default.host'))->not->toBe('127.0.0.1');

    expect(RedisCheck::new()->run()->status->value)->toBe(Status::ok()->value);
});
