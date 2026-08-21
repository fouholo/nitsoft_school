<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Arabic\Models\ArabicSubject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArabicSubject>
 */
class ArabicSubjectFactory extends Factory
{
    protected $model = ArabicSubject::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'abbreviation' => strtoupper(fake()->unique()->lexify('???')),
        ];
    }
}
