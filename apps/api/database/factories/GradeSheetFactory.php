<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\Term;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeSheet>
 */
class GradeSheetFactory extends Factory
{
    protected $model = GradeSheet::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'classroom_id' => Classroom::factory(),
            'subject_id' => Subject::factory(),
            'term_id' => Term::factory(),
            'teacher_id' => User::factory(),
            'title' => 'Devoir '.fake()->numberBetween(1, 5),
            'max_score' => 20,
            'weight' => 1,
            'graded_on' => now()->toDateString(),
        ];
    }
}
