<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aggiunge la soglia di scorta bassa usata dalla cassa.
 *
 * Nasce a 0, cioè avviso disattivato: su un'installazione già in uso l'aspetto
 * della cassa non deve cambiare finché un admin non decide la soglia.
 * insertOrIgnore perché configs.code è unico e la riga può già esistere (il
 * seeder è idempotente e viene rieseguito).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configs')->insertOrIgnore([
            'code' => 'low_stock_threshold',
            'config_value' => '0',
            'comment' => 'numero di pezzi rimasti sotto il quale la cassa avvisa (scorta bassa), 0 disattiva',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('configs')->where('code', 'low_stock_threshold')->delete();
    }
};
