<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Arabic\Models\ArabicGrade;
use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArabicGrade>
 */
class ArabicGradeFactory extends Factory
{
    protected $model = ArabicGrade::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'arabic_grade_sheet_id' => ArabicGradeSheet::factory(),
            'enrollment_id' => Enrollment::factory(),
            'score' => fake()->randomFloat(2, 0, 20),
        ];
    }
}
