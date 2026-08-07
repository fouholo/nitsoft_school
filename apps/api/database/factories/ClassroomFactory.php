<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Serie;
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
            'level_id' => Level::factory(),
            'serie_id' => null,
            'numero' => (string) fake()->numberBetween(1, 6),
            'capacity' => 30,
        ];
    }

    public function prescolaire(): static
    {
        return $this->state(fn (): array => [
            'level_id' => Level::factory()->prescolaire(),
            'numero' => fake()->randomElement(['A', 'B']),
        ]);
    }

    public function primaire(): static
    {
        return $this->state(fn (): array => [
            'level_id' => Level::factory()->primaire(),
            'numero' => fake()->randomElement(['A', 'B']),
        ]);
    }

    public function terminale(): static
    {
        return $this->state(fn (): array => [
            'level_id' => Level::factory()->terminale(),
            'serie_id' => Serie::factory(),
            'numero' => fake()->randomElement(['1', '2']),
        ]);
    }
}
