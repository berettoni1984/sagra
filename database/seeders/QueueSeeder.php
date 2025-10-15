<?php

namespace Database\Seeders;

use App\Models\Queue;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QueueSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Queue::firstOrCreate(
            [
                'comment' => 'default',
            ],
            [
                'order_number' => '0',
                'reset_at' => now(),
                'is_disabled' => false,
                'name' => '',
            ]
        );
    }
}
