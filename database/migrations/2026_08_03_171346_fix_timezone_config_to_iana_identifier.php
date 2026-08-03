<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Converte il fuso orario salvato da abbreviazione a identificativo IANA.
 *
 * 'CEST' è accettato da PHP ma come offset fisso +02:00, privo di regole di ora
 * legale: da fine ottobre a fine marzo ogni timestamp mostrato o esportato
 * risultava un'ora avanti rispetto allo scontrino, e i filtri per intervallo di
 * date includevano/escludevano l'ora sbagliata a cavallo della mezzanotte.
 * 'Europe/Rome' gestisce automaticamente il passaggio CET/CEST.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const REPLACEMENTS = [
        'CEST' => 'Europe/Rome',
        'CET' => 'Europe/Rome',
    ];

    public function up(): void
    {
        foreach (self::REPLACEMENTS as $abbreviation => $identifier) {
            DB::table('configs')
                ->where('code', 'timezone')
                ->where('config_value', $abbreviation)
                ->update(['config_value' => $identifier]);
        }
    }

    public function down(): void
    {
        DB::table('configs')
            ->where('code', 'timezone')
            ->where('config_value', 'Europe/Rome')
            ->update(['config_value' => 'CEST']);
    }
};
