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
            // Come fa il form: il logo nuovo si accoda agli esistenti, così il
            // primo in ordine (quello stampato) resta quello già configurato.
            'order' => static fn (): int => (int) Logo::max('order') + 1,
        ];
    }

    public function atOrder(int $order): static
    {
        return $this->state(['order' => $order]);
    }
}
