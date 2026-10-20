<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArabicClassroom>
 */
class ArabicClassroomFactory extends Factory
{
    protected $model = ArabicClassroom::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'school_year_id' => SchoolYear::factory(),
            'arabic_level_id' => ArabicLevel::factory(),
            'arabic_serie_id' => null,
            'numero' => (string) fake()->numberBetween(1, 6),
            'capacity' => 30,
        ];
    }
}
