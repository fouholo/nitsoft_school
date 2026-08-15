<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Models\PrimaryGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrimaryGrade>
 */
class PrimaryGradeFactory extends Factory
{
    protected $model = PrimaryGrade::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'grade_sheet_id' => GradeSheet::factory(),
            'student_id' => Student::factory(),
            'primary_subject_id' => PrimarySubject::factory(),
            'score' => fake()->randomFloat(2, 0, 20),
        ];
    }
}
