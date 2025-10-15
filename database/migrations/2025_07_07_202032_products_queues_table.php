<?php

use App\Models\Product;
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
        Schema::create('product_queue', function (Blueprint $table) {
            $table->foreignId('product_id');
            $table->foreignId('queue_id');
        });
        $queues = \App\Models\Queue::all();
        if (! $queues) {
            return;
        }
        Product::all()->each(function ($product) use ($queues) {
            $product->queues()->attach($queues);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('product_queue');
    }
};
