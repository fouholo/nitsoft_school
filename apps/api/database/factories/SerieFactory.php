<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\Serie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Serie>
 */
class SerieFactory extends Factory
{
    protected $model = Serie::class;

    public function definition(): array
    {
        return [
            'serie' => strtoupper(fake()->unique()->lexify('S?')),
            'serie_wording' => fake()->unique()->words(2, true),
        ];
    }
}
