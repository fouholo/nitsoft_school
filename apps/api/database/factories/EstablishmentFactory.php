<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Establishment>
 */
class EstablishmentFactory extends Factory
{
    protected $model = Establishment::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'type' => fake()->randomElement(['primaire', 'college', 'lycee']),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'timezone' => 'UTC',
            'is_active' => true,
        ];
    }
}
