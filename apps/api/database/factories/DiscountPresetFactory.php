<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Models\DiscountPreset;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountPreset>
 */
class DiscountPresetFactory extends Factory
{
    protected $model = DiscountPreset::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'name' => fake()->unique()->words(3, true),
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
