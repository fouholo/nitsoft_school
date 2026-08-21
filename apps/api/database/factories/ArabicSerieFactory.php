<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Arabic\Models\ArabicSerie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArabicSerie>
 */
class ArabicSerieFactory extends Factory
{
    protected $model = ArabicSerie::class;

    public function definition(): array
    {
        return [
            'serie' => strtoupper(fake()->unique()->lexify('S?')),
            'serie_wording' => fake()->unique()->words(2, true),
        ];
    }
}
