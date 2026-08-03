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
            'is_default' => false,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['is_disabled' => true]);
    }

    public function isDefault(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function resetAt(\DateTimeInterface|string $moment): static
    {
        return $this->state(['reset_at' => $moment]);
    }
}
