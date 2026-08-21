<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArabicTerm>
 */
class ArabicTermFactory extends Factory
{
    protected $model = ArabicTerm::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'school_year_id' => SchoolYear::factory(),
            'label' => fake()->randomElement(['Période 1', 'Période 2', 'Période 3']),
            'sequence' => fake()->numberBetween(1, 3),
        ];
    }
}
