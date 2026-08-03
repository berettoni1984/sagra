<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;
use Spatie\SecurityAdvisoriesHealthCheck\SecurityAdvisoriesCheck;

/**
 * Controlli di stato dell'applicazione.
 *
 * Durante una sagra il pannello è l'unico punto vendita: se il database o
 * Horizon si fermano, la cassa si blocca. Questi controlli servono ad
 * accorgersene prima che lo faccia la fila di persone davanti al banco.
 */
class HealthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Health::checks($this->checks());
    }

    /**
     * @return array<int, Check>
     */
    private function checks(): array
    {
        $checks = [
            // Senza database la cassa non emette ordini.
            DatabaseCheck::new(),

            // Redis regge le code: se cade, Horizon si ferma con lui.
            RedisCheck::new(),

            // Horizon elabora export e notifiche.
            HorizonCheck::new(),

            CacheCheck::new(),

            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(70)
                ->failWhenUsedSpaceIsAbovePercentage(90),

            // Verifica che lo scheduler stia girando: si affida al battito
            // registrato dal comando health:schedule-check-heartbeat.
            ScheduleCheck::new(),

            // Segnala le dipendenze con vulnerabilità note.
            SecurityAdvisoriesCheck::new(),
        ];

        // Questi tre sono verifiche di igiene di produzione: in locale
        // fallirebbero per definizione (debug attivo, ambiente non di
        // produzione, cache di config e rotte non generate).
        if ($this->app->isProduction()) {
            $checks[] = DebugModeCheck::new();
            $checks[] = EnvironmentCheck::new();
            $checks[] = OptimizedAppCheck::new();
        }

        return $checks;
    }
}
