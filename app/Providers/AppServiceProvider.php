<?php

namespace App\Providers;

use App\Models\Config;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // La cache dei valori Config vive nel processo: un worker Horizon resta
        // in piedi per molti job, quindi va svuotata all'inizio di ognuno,
        // altrimenti un export userebbe una configurazione vecchia.
        Event::listen(JobProcessing::class, static function (): void {
            Config::flushValueCache();
        });
    }
}
