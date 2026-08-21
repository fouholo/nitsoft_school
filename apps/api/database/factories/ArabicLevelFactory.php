<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Arabic\Models\ArabicLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArabicLevel>
 */
class ArabicLevelFactory extends Factory
{
    protected $model = ArabicLevel::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('AR??')),
            'wording' => fake()->unique()->words(2, true),
            'cycle' => Cycle::Secondaire->value,
            'requires_series' => false,
        ];
    }
}
