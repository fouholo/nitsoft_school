<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAssignment>
 */
class TeacherAssignmentFactory extends Factory
{
    protected $model = TeacherAssignment::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'user_id' => User::factory(),
            'classroom_id' => Classroom::factory(),
            'subject_id' => Subject::factory(),
            'school_year_id' => SchoolYear::factory(),
        ];
    }
}
