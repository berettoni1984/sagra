<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'stock' => fake()->numberBetween(10, 500),
            'is_disabled' => false,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['is_disabled' => true]);
    }

    public function exhausted(): static
    {
        return $this->state(['stock' => 0]);
    }
}
