<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\Subject;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'name' => fake()->randomElement(['Mathématiques', 'Français', 'Histoire', 'Sciences']),
        ];
    }
}
