<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\SubjectCoefficient;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubjectCoefficient>
 */
class SubjectCoefficientFactory extends Factory
{
    protected $model = SubjectCoefficient::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'level_id' => Level::factory(),
            'serie_id' => null,
            'subject_id' => Subject::factory(),
            'coefficient' => 1,
        ];
    }
}
