<?php

namespace Database\Factories;

use App\Models\Config;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Config>
 */
class ConfigFactory extends Factory
{
    protected $model = Config::class;

    public function definition(): array
    {
        return [
            // configs.code è univoco e config_value è NOT NULL
            'code' => fake()->unique()->slug(1),
            'config_value' => fake()->word(),
            'comment' => null,
        ];
    }

    public function of(string $code, string $value): static
    {
        return $this->state(['code' => $code, 'config_value' => $value]);
    }
}
