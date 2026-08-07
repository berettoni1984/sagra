<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('is_disabled');
            $table->index('order');
        });

        // La coda marcata come default diventa la prima della lista: da qui in
        // poi e' la posizione piu' bassa a fare da predefinita, senza flag.
        $ids = DB::table('queues')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $posizione => $id) {
            DB::table('queues')->where('id', $id)->update(['order' => $posizione + 1]);
        }

        Schema::table('queues', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_disabled');
        });

        $primo = DB::table('queues')->orderBy('order')->orderBy('id')->value('id');

        if ($primo !== null) {
            DB::table('queues')->where('id', $primo)->update(['is_default' => true]);
        }

        Schema::table('queues', function (Blueprint $table) {
            $table->dropIndex(['order']);
            $table->dropColumn('order');
        });
    }
};
