<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArabicGradeSheet>
 */
class ArabicGradeSheetFactory extends Factory
{
    protected $model = ArabicGradeSheet::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'arabic_level_id' => ArabicLevel::factory(),
            'arabic_subject_id' => ArabicSubject::factory(),
            'arabic_term_id' => ArabicTerm::factory(),
            'teacher_id' => User::factory(),
            'title' => 'Devoir '.fake()->numberBetween(1, 5),
            'type' => 'devoir',
            'max_score' => 20,
            'weight' => 1,
            'graded_on' => now()->toDateString(),
        ];
    }
}
