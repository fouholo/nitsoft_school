<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolYear>
 */
class SchoolYearFactory extends Factory
{
    protected $model = SchoolYear::class;

    public function definition(): array
    {
        return [
            'label' => fake()->unique()->year().'-'.((int) fake()->year() + 1),
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_current' => true,
        ];
    }
}
