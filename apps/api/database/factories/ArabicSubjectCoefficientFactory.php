<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicSubjectCoefficient;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArabicSubjectCoefficient>
 */
class ArabicSubjectCoefficientFactory extends Factory
{
    protected $model = ArabicSubjectCoefficient::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'arabic_level_id' => ArabicLevel::factory(),
            'arabic_serie_id' => null,
            'arabic_subject_id' => ArabicSubject::factory(),
            'coefficient' => 1,
        ];
    }
}
