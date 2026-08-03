<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->randomFloat(2, 1, 20),
            // products.stock è smallint: si resta ben dentro il range
            'stock' => fake()->numberBetween(10, 500),
            'backorder' => false,
            'is_disabled' => false,
            'order' => 0,
        ];
    }

    /** Giacenza ignorata: nessun controllo di disponibilità. */
    public function backorder(): static
    {
        return $this->state(['backorder' => true]);
    }

    public function disabled(): static
    {
        return $this->state(['is_disabled' => true]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stock' => 0, 'backorder' => false]);
    }
}
