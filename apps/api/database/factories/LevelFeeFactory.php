<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LevelFee>
 */
class LevelFeeFactory extends Factory
{
    protected $model = LevelFee::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'school_year_id' => SchoolYear::factory(),
            'level_id' => Level::factory(),
            'registration_amount' => 10000,
        ];
    }
}
