<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Level>
 */
class LevelFactory extends Factory
{
    protected $model = Level::class;

    public function definition(): array
    {
        return [
            'level' => strtoupper(fake()->unique()->lexify('LV??')),
            'level_wording' => fake()->unique()->words(2, true),
            'cycle' => Cycle::Secondaire->value,
            'requires_series' => false,
        ];
    }

    public function prescolaire(): static
    {
        return $this->state(fn (): array => [
            'cycle' => Cycle::Prescolaire->value,
            'requires_series' => false,
        ]);
    }

    public function primaire(): static
    {
        return $this->state(fn (): array => [
            'cycle' => Cycle::Primaire->value,
            'requires_series' => false,
        ]);
    }

    public function terminale(): static
    {
        return $this->state(fn (): array => [
            'level' => 'TLE'.fake()->unique()->numerify('##'),
            'level_wording' => 'Terminale',
            'cycle' => Cycle::Secondaire->value,
            'requires_series' => true,
        ]);
    }
}
