<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allinea le regole ON DELETE delle foreign key.
 *
 * Criterio: colonna NOT NULL -> cascadeOnDelete (non potendo essere azzerata,
 * l'unica alternativa a lasciare righe irraggiungibili è rimuoverle); colonna
 * nullable -> nullOnDelete.
 *
 * Entrambe queste FK erano state create con -&gt;constrained() senza regola, cioè
 * RESTRICT: cancellare una coda con ordini, o forzare la cancellazione di un
 * ordine con righe, terminava con l'errore SQL 1451 e una pagina 500.
 *
 * order_items.product_id resta com'è: già nullable e già SET NULL, così la riga
 * d'ordine (che è un dato contabile, con nome e prezzo storicizzati)
 * sopravvive alla cancellazione del prodotto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', static function (Blueprint $table): void {
            // NOT NULL -> cascade. Coerente con Order::booted(), che alla
            // cancellazione dell'ordine elimina già le righe figlie; il
            // forceDelete invece finiva in errore perché le lasciava indietro.
            $table->dropForeign(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });

        Schema::table('orders', static function (Blueprint $table): void {
            // nullable -> set null, come la colonna sorella user_id.
            $table->dropForeign(['queue_id']);
            $table->foreign('queue_id')->references('id')->on('queues')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', static function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders');
        });

        Schema::table('orders', static function (Blueprint $table): void {
            $table->dropForeign(['queue_id']);
            $table->foreign('queue_id')->references('id')->on('queues');
        });
    }
};
