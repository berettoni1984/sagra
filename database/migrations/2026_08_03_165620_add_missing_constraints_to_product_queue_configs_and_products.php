<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vincoli mancanti su product_queue, configs e products.
 *
 * product_queue era stata creata con foreignId() senza constrained(): nessuna
 * foreign key, quindi nessun indice e nessun vincolo di unicità. Di conseguenza
 * le righe sopravvivono alla cancellazione dei prodotti e la stessa coppia
 * (prodotto, coda) può essere inserita più volte, duplicando il prodotto nel POS
 * e raddoppiando lo scarico degli ingredienti.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Righe orfane: vanno rimosse prima di poter creare la foreign key.
        // Puntano a prodotti/code che non esistono più, quindi non sono
        // recuperabili e alimentano solo il whereIn del filtro per coda.
        DB::table('product_queue')
            ->whereNotIn('product_id', DB::table('products')->select('id'))
            ->orWhereNotIn('queue_id', DB::table('queues')->select('id'))
            ->delete();

        // 2. Coppie duplicate: la tabella non ha chiave primaria, quindi si
        // ricostruisce il contenuto a partire dall'insieme distinto.
        $distinct = DB::table('product_queue')
            ->select('product_id', 'queue_id')
            ->distinct()
            ->get()
            ->map(static fn (object $row): array => [
                'product_id' => $row->product_id,
                'queue_id' => $row->queue_id,
            ])
            ->all();

        DB::transaction(static function () use ($distinct): void {
            // DELETE e non TRUNCATE: resta dentro la transazione.
            DB::table('product_queue')->delete();
            foreach (array_chunk($distinct, 500) as $chunk) {
                DB::table('product_queue')->insert($chunk);
            }
        });

        Schema::table('product_queue', static function (Blueprint $table): void {
            $table->unique(['product_id', 'queue_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('queue_id')->references('id')->on('queues')->cascadeOnDelete();
        });

        // 3. configs.code: ogni lettura è whereCode(...)->first(), cioè "vince la
        // riga con id più basso". Con codici duplicati le modifiche sulla riga
        // più recente non hanno alcun effetto visibile. Si tengono le righe già
        // in uso (id minimo) così il comportamento attuale non cambia.
        $duplicated = DB::table('configs')
            ->select('code', DB::raw('MIN(id) as keep_id'))
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicated as $row) {
            DB::table('configs')
                ->where('code', $row->code)
                ->where('id', '>', $row->keep_id)
                ->delete();
        }

        Schema::table('configs', static function (Blueprint $table): void {
            $table->unique('code');
        });

        // 4. products.order era NOT NULL senza default: qualunque create() che
        // ometta la colonna finiva in errore SQL 1364.
        Schema::table('products', static function (Blueprint $table): void {
            $table->integer('order')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_queue', static function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['queue_id']);
            $table->dropUnique(['product_id', 'queue_id']);
        });

        // Eliminare la foreign key non rimuove l'indice che MySQL le aveva
        // creato: senza questo la tabella non torna allo stato originale.
        $leftover = collect(Schema::getIndexes('product_queue'))
            ->pluck('name')
            ->first(static fn (string $name): bool => $name === 'product_queue_queue_id_foreign');

        if ($leftover !== null) {
            Schema::table('product_queue', static function (Blueprint $table): void {
                $table->dropIndex('product_queue_queue_id_foreign');
            });
        }

        Schema::table('configs', static function (Blueprint $table): void {
            $table->dropUnique(['code']);
        });

        Schema::table('products', static function (Blueprint $table): void {
            $table->integer('order')->change();
        });
    }
};
