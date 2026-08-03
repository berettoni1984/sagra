<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Queue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $total = fake()->randomFloat(2, 1, 100);

        return [
            // queues.order_number è smallint unsigned: si resta nel range
            'number' => fake()->numberBetween(1, 60000),
            'queue_id' => Queue::factory(),
            'user_id' => null,
            'total_amount' => $total,
            'total_paid' => $total,
            'note' => null,
        ];
    }

    /** Ordine gratuito o parzialmente pagato. */
    public function paid(float $amount): static
    {
        return $this->state(['total_paid' => $amount]);
    }

    public function withoutQueue(): static
    {
        return $this->state(['queue_id' => null]);
    }

    public function createdAt(\DateTimeInterface|string $moment): static
    {
        return $this->state(['created_at' => $moment]);
    }
}
