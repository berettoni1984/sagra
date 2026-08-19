<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rende products.name una chiave utilizzabile dall'import.
 *
 * L'indice unico su products.name esiste già (2026_08_03_214815), ma la sua
 * semantica dipendeva dalla collation con cui la colonna era stata creata: qui
 * la si fissa su una collation _ci, altrimenti su un database con collation
 * binaria "PANINO" e "Panino" resterebbero due prodotti distinti e
 * l'importazione — che ora ritrova i prodotti solo dal nome, senza colonna id —
 * ne creerebbe un doppione a ogni reimportazione con maiuscole diverse.
 *
 * In più si ripuliscono i nomi già salvati: la collation _ci ignora gli spazi
 * in coda ma non quelli iniziali, quindi " Panino" poteva convivere con
 * "Panino" e sfuggire al confronto per nome.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->trimNomiEsistenti();

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE products MODIFY name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL'
        );
    }

    /**
     * Non si torna indietro: la collation case-sensitive reintrodurrebbe la
     * possibilità di nomi duplicati e gli spazi rimossi non sono ricostruibili.
     */
    public function down(): void {}

    private function trimNomiEsistenti(): void
    {
        $prodotti = DB::table('products')->orderBy('id')->get(['id', 'name']);

        foreach ($prodotti as $prodotto) {
            $originale = (string) $prodotto->name;
            $pulito = (string) preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $originale);

            if ($pulito === $originale) {
                continue;
            }

            // products.name è NOT NULL: un nome fatto di soli spazi va comunque
            // riempito con qualcosa di riconoscibile.
            if ($pulito === '') {
                $pulito = 'Prodotto '.$prodotto->id;
            }

            DB::table('products')
                ->where('id', $prodotto->id)
                ->update(['name' => $this->nomeLibero($pulito, (int) $prodotto->id)]);
        }
    }

    /**
     * Il nome ripulito può essere già occupato da un altro prodotto (" Panino"
     * accanto a "Panino"): si aggiunge un suffisso invece di cancellare, perché
     * la riga ha storico e associazioni alle code.
     */
    private function nomeLibero(string $base, int $id): string
    {
        if (! $this->occupato($base, $id)) {
            return $base;
        }

        $suffisso = 2;

        do {
            $candidato = mb_substr($base, 0, 240).' ('.$suffisso.')';
            $suffisso++;
        } while ($this->occupato($candidato, $id));

        return $candidato;
    }

    private function occupato(string $name, int $id): bool
    {
        return DB::table('products')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->where('id', '!=', $id)
            ->exists();
    }
};
