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
        Schema::table('logos', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('path');
            $table->index('order');
        });

        // Il logo marcato come default diventa il primo della lista: da qui in
        // poi e' la posizione piu' bassa a finire sugli scontrini.
        $ids = DB::table('logos')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $posizione => $id) {
            DB::table('logos')->where('id', $id)->update(['order' => $posizione + 1]);
        }

        Schema::table('logos', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logos', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('path');
        });

        $primo = DB::table('logos')->orderBy('order')->orderBy('id')->value('id');

        if ($primo !== null) {
            DB::table('logos')->where('id', $primo)->update(['is_default' => true]);
        }

        Schema::table('logos', function (Blueprint $table) {
            $table->dropIndex(['order']);
            $table->dropColumn('order');
        });
    }
};
