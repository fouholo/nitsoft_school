<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'relationship' => fake()->randomElement(['pere', 'mere', 'tuteur']),
        ];
    }
}
