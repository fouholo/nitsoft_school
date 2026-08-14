<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Sciences', 'Lettres', 'Sciences Humaines', 'Langues', 'EPS/Arts']),
        ];
    }
}
