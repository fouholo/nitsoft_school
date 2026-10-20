<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Billing\Models\ArabicLevelFee;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArabicLevelFee>
 */
class ArabicLevelFeeFactory extends Factory
{
    protected $model = ArabicLevelFee::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'school_year_id' => SchoolYear::factory(),
            'arabic_level_id' => ArabicLevel::factory(),
            'registration_amount' => 10000,
        ];
    }
}
