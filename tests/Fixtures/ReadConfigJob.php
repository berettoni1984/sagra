<?php

namespace Tests\Fixtures;

use App\Models\Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Job di appoggio: registra il valore di configurazione visto durante
 * l'esecuzione, per verificare che la cache memoizzata venga svuotata
 * all'inizio di ogni job (un worker Horizon resta in piedi per molti job).
 */
class ReadConfigJob implements ShouldQueue
{
    use Queueable;

    public static ?string $seen = null;

    public function __construct(private string $code) {}

    public function handle(): void
    {
        self::$seen = Config::value($this->code);
    }
}
