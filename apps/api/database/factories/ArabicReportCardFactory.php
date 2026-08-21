<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Arabic\Models\ArabicReportCard;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArabicReportCard>
 */
class ArabicReportCardFactory extends Factory
{
    protected $model = ArabicReportCard::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'enrollment_id' => Enrollment::factory(),
            'arabic_term_id' => ArabicTerm::factory(),
            'average' => fake()->randomFloat(2, 5, 20),
            'rank' => fake()->numberBetween(1, 30),
            'generated_at' => now(),
        ];
    }
}
