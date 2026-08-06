<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    protected $model = Classroom::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'school_year_id' => SchoolYear::factory(),
            'name' => fake()->randomElement(['6ème A', '5ème B', 'Terminale C']),
            'level' => fake()->randomElement(['6ème', '5ème', 'Terminale']),
            'cycle' => Cycle::Secondaire,
            'capacity' => 30,
        ];
    }

    public function prescolaire(): static
    {
        return $this->state(fn (): array => [
            'name' => fake()->randomElement(['Petite Section A', 'Grande Section B']),
            'level' => fake()->randomElement(['Petite Section', 'Grande Section']),
            'cycle' => Cycle::Prescolaire,
        ]);
    }

    public function primaire(): static
    {
        return $this->state(fn (): array => [
            'name' => fake()->randomElement(['CP A', 'CM2 B']),
            'level' => fake()->randomElement(['CP', 'CM2']),
            'cycle' => Cycle::Primaire,
        ]);
    }
}
