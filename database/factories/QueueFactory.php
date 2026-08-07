<?php

namespace Database\Factories;

use App\Models\Queue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Queue>
 */
class QueueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            // queues.comment è NOT NULL
            'comment' => fake()->unique()->word(),
            'order_number' => 0,
            'reset_at' => null,
            'is_disabled' => false,
            // Come fa il form: la coda nuova si accoda alle esistenti, così la
            // predefinita (la prima in ordine) resta quella già configurata.
            'order' => static fn (): int => (int) Queue::max('order') + 1,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['is_disabled' => true]);
    }

    public function atOrder(int $order): static
    {
        return $this->state(['order' => $order]);
    }

    public function resetAt(\DateTimeInterface|string $moment): static
    {
        return $this->state(['reset_at' => $moment]);
    }
}
