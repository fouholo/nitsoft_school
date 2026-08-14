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
        $names = [
            'Mathématiques' => 'MATHS',
            'Français' => 'FR',
            'Histoire' => 'HIST',
            'Sciences' => 'SCI',
            'Anglais' => 'ANGL',
            'Philosophie' => 'PHILO',
        ];

        $name = fake()->unique()->randomElement(array_keys($names));

        return [
            'name' => $name,
            'abbreviation' => $names[$name],
            'is_prescolaire_primaire' => true,
            'is_secondaire' => true,
        ];
    }
}
