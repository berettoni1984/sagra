<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allinea queues.reset_at al tipo delle altre colonne temporali.
     *
     * reset_at era l'unica colonna di data dichiarata DATETIME: MySQL la salva
     * alla lettera, mentre created_at/updated_at sono TIMESTAMP e vengono
     * convertite in UTC in scrittura e riconvertite nel fuso della sessione in
     * lettura. Con le due sponde su tipi diversi basta un cambio di fuso — un
     * APP_TIMEZONE modificato, un container riavviato, un dump di produzione
     * ripristinato su un MySQL con fuso diverso — per spostarne una sola, e il
     * confronto "orders.created_at >= queues.reset_at" che conta i venduti in
     * cassa finisce fuori asse: con reset_at due ore avanti ogni ordine appena
     * battuto risultava anteriore all'azzeramento e i venduti restavano a 0.
     */
    public function up(): void
    {
        // Un azzeramento nel futuro non può esistere: è il residuo dello
        // sfasamento. NULL vale "coda mai azzerata", quindi il conteggio riparte
        // da tutto lo storico della fila e il prossimo azzeramento riscrive un
        // valore sano.
        DB::table('queues')
            ->whereNotNull('reset_at')
            ->where('reset_at', '>', now())
            ->update(['reset_at' => null]);

        Schema::table('queues', function (Blueprint $table) {
            $table->timestamp('reset_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            $table->dateTime('reset_at')->nullable()->change();
        });
    }
};
