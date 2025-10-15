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
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('comment');
            $table->unsignedSmallInteger('order_number');
            $table->dateTime('reset_at')->nullable();
            $table->boolean('is_disabled')->default(false);
            $table->timestamps();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('queue_id')->nullable()->constrained();
        });
        // Run the DatabaseSeeder
        \Artisan::call('db:seed', [
            '--class' => \Database\Seeders\DatabaseSeeder::class,
            '--force' => true,
        ]);
        // Run the DatabaseSeeder
        \Artisan::call('db:seed', [
            '--class' => \Database\Seeders\QueueSeeder::class,
            '--force' => true,
        ]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('orders_queue_id_foreign');
            $table->dropColumn('queue_id');
        });
        Schema::dropIfExists('queues');
    }
};
