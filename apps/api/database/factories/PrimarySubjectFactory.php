<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\PrimarySubject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrimarySubject>
 */
class PrimarySubjectFactory extends Factory
{
    protected $model = PrimarySubject::class;

    public function definition(): array
    {
        $names = [
            'Mathématiques' => 'MATHS',
            'Français' => 'FR',
            'Éducation civique et morale' => 'ECM',
            'Sciences' => 'SCI',
            'Histoire-Géographie' => 'HG',
        ];

        $name = fake()->unique()->randomElement(array_keys($names));

        return [
            'name' => $name,
            'abbreviation' => $names[$name],
            'coefficient_cp1' => null,
            'coefficient_cp2' => null,
            'coefficient_ce1' => null,
            'coefficient_ce2' => null,
            'coefficient_cm1' => null,
            'coefficient_cm2' => null,
        ];
    }
}
