<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\Grade;
use App\Domain\Grading\Models\GradeSheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'grade_sheet_id' => GradeSheet::factory(),
            'student_id' => Student::factory(),
            'score' => fake()->randomFloat(2, 0, 20),
        ];
    }
}
