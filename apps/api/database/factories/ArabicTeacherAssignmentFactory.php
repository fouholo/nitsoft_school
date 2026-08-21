<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArabicTeacherAssignment>
 */
class ArabicTeacherAssignmentFactory extends Factory
{
    protected $model = ArabicTeacherAssignment::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'user_id' => User::factory(),
            'arabic_level_id' => ArabicLevel::factory(),
            'arabic_subject_id' => ArabicSubject::factory(),
            'school_year_id' => SchoolYear::factory(),
        ];
    }
}
