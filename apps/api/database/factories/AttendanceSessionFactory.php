<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    protected $model = AttendanceSession::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'classroom_id' => Classroom::factory(),
            'teacher_id' => User::factory(),
            'session_date' => now()->toDateString(),
        ];
    }
}
