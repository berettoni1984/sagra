<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->boolean('is_disabled')->default(false)->after('stock');
        });
        Schema::table('product_ingredient', function (Blueprint $table) {
            $table->foreignId('product_id')->change()->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('ingredient_id')->change()->constrained('ingredients')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('is_disabled');
        });
        Schema::table('product_ingredient', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['ingredient_id']);
            $table->foreignId('product_id')->change();
            $table->foreignId('ingredient_id')->change();
        });
    }
};
