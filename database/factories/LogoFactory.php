<?php

namespace Database\Factories;

use App\Models\Logo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Logo>
 */
class LogoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => 'logos/'.fake()->unique()->slug(2).'.png',
            'is_default' => false,
        ];
    }

    public function isDefault(): static
    {
        return $this->state(['is_default' => true]);
    }
}
