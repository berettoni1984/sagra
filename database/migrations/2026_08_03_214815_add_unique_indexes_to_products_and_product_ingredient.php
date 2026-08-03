<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vincoli di unicità mancanti su products e product_ingredient.
 *
 * - product_ingredient non aveva un indice unico: lo stesso ingrediente si
 *   poteva collegare due volte allo stesso prodotto, raddoppiando lo scarico
 *   di magazzino a ogni vendita e rendendo ambigua la modifica della qty
 *   (la pivot non ha chiave propria, quindi si aggiornavano entrambe le righe).
 * - products.name non aveva un indice unico (era stato rimosso): reimportando
 *   un CSV con la colonna id vuota il catalogo si duplicava silenziosamente.
 * - products.order non era indicizzato pur essendo l'ordinamento predefinito
 *   dell'elenco prodotti e della griglia di cassa.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dedupeProductIngredient();

        Schema::table('product_ingredient', static function (Blueprint $table): void {
            $table->unique(['product_id', 'ingredient_id']);
        });

        $this->disambiguateProductNames();

        Schema::table('products', static function (Blueprint $table): void {
            $table->unique('name');
            $table->index('order');
        });
    }

    public function down(): void
    {
        Schema::table('product_ingredient', static function (Blueprint $table): void {
            $table->dropUnique(['product_id', 'ingredient_id']);
        });

        Schema::table('products', static function (Blueprint $table): void {
            $table->dropUnique(['name']);
            $table->dropIndex(['order']);
        });
    }

    /**
     * Ricostruisce la pivot a partire dall'insieme distinto, tenendo la qty
     * più alta fra le righe duplicate (l'ipotesi prudente: non scaricare meno
     * ingrediente di quanto una delle due righe prevedeva).
     */
    private function dedupeProductIngredient(): void
    {
        $distinct = DB::table('product_ingredient')
            ->select('product_id', 'ingredient_id', DB::raw('MAX(qty) as qty'))
            ->groupBy('product_id', 'ingredient_id')
            ->get()
            ->map(static fn (object $row): array => [
                'product_id' => $row->product_id,
                'ingredient_id' => $row->ingredient_id,
                'qty' => $row->qty,
            ])
            ->all();

        DB::transaction(static function () use ($distinct): void {
            // DELETE e non TRUNCATE: resta dentro la transazione
            DB::table('product_ingredient')->delete();
            foreach (array_chunk($distinct, 500) as $chunk) {
                DB::table('product_ingredient')->insert($chunk);
            }
        });
    }

    /**
     * Rende univoci i nomi già presenti aggiungendo un suffisso numerico.
     *
     * Si rinomina invece di cancellare: le righe duplicate potrebbero avere
     * associazioni o storico, e una cancellazione non sarebbe reversibile.
     * La riga con id più basso conserva il nome originale.
     */
    private function disambiguateProductNames(): void
    {
        $duplicati = DB::table('products')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicati as $name) {
            $ids = DB::table('products')
                ->where('name', $name)
                ->orderBy('id')
                ->pluck('id')
                ->skip(1); // il primo mantiene il nome originale

            foreach ($ids as $id) {
                DB::table('products')
                    ->where('id', $id)
                    ->update(['name' => $this->nomeLibero($name)]);
            }
        }
    }

    private function nomeLibero(string $base): string
    {
        $suffisso = 2;

        do {
            $candidato = mb_substr($base, 0, 240).' ('.$suffisso.')';
            $suffisso++;
        } while (DB::table('products')->where('name', $candidato)->exists());

        return $candidato;
    }
};
