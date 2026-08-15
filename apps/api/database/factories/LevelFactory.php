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

    /** @var array<int, string> */
    private const PRIMAIRE_LEVELS = ['CP1', 'CP2', 'CE1', 'CE2', 'CM1', 'CM2'];

    private static int $primaireLevelIndex = 0;

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

    /**
     * Utilise un vrai code de niveau primaire (CP1..CM2, cycliquement) —
     * requis par PrimarySubject::coefficientColumn(), qui n'accepte que ces
     * codes exacts.
     */
    public function primaire(): static
    {
        return $this->state(function (): array {
            $code = self::PRIMAIRE_LEVELS[self::$primaireLevelIndex % count(self::PRIMAIRE_LEVELS)];
            self::$primaireLevelIndex++;

            return [
                'level' => $code,
                'level_wording' => $code,
                'cycle' => Cycle::Primaire->value,
                'requires_series' => false,
            ];
        });
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
