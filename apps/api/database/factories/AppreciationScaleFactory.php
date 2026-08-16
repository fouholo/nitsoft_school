<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Grading\Models\AppreciationScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppreciationScale>
 */
class AppreciationScaleFactory extends Factory
{
    protected $model = AppreciationScale::class;

    public function definition(): array
    {
        return [
            'percentage' => fake()->unique()->numberBetween(0, 100),
            'appreciation' => fake()->randomElement(['Excellent', 'Très bien', 'Bien', 'Assez bien', 'Passable', 'Médiocre', 'Insuffisant']),
            'tableau_honneur' => false,
            'tableau_excellence' => false,
            'felicitation' => false,
            'encouragement' => false,
        ];
    }
}
