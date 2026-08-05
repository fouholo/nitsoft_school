<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Term;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\ReportCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportCard>
 */
class ReportCardFactory extends Factory
{
    protected $model = ReportCard::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'student_id' => Student::factory(),
            'term_id' => Term::factory(),
            'classroom_id' => Classroom::factory(),
            'average' => fake()->randomFloat(2, 8, 18),
            'rank' => fake()->numberBetween(1, 30),
            'generated_at' => now(),
        ];
    }
}
