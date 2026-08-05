<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Establishments\Models\Foundation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Foundation>
 */
class FoundationFactory extends Factory
{
    protected $model = Foundation::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Group',
            'slug' => fake()->unique()->slug(),
            'is_active' => true,
        ];
    }
}
