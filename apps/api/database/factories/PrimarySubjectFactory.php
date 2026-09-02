<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Academics\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrimarySubject>
 */
class PrimarySubjectFactory extends Factory
{
    protected $model = PrimarySubject::class;

    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory()->state(['is_prescolaire_primaire' => true]),
            'coefficient_cp1' => null,
            'coefficient_cp2' => null,
            'coefficient_ce1' => null,
            'coefficient_ce2' => null,
            'coefficient_cm1' => null,
            'coefficient_cm2' => null,
        ];
    }
}
