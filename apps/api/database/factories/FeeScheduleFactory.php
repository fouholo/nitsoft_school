<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\FeeSchedule;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeSchedule>
 */
class FeeScheduleFactory extends Factory
{
    protected $model = FeeSchedule::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'school_year_id' => SchoolYear::factory(),
            'label' => 'Frais de scolarité',
            'amount' => 50000,
            'due_date' => now()->addMonth()->toDateString(),
        ];
    }
}
