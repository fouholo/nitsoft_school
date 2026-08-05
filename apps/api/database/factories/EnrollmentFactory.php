<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'student_id' => Student::factory(),
            'classroom_id' => Classroom::factory(),
            'school_year_id' => SchoolYear::factory(),
            'enrolled_on' => now()->toDateString(),
            'status' => 'active',
        ];
    }
}
