<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\Subject;
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
            'name' => fake()->unique()->randomElement(['Mathématiques', 'Français', 'Histoire', 'Sciences', 'Anglais', 'Philosophie']),
            'is_prescolaire_primaire' => true,
            'is_secondaire' => true,
        ];
    }
}
