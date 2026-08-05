<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Term;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    protected $model = Term::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'school_year_id' => SchoolYear::factory(),
            'label' => fake()->randomElement(['Trimestre 1', 'Trimestre 2', 'Trimestre 3']),
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-12-01',
            'sequence' => fake()->numberBetween(1, 3),
        ];
    }
}
