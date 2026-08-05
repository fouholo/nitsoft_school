<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Notifications\Models\SmsTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsTemplate>
 */
class SmsTemplateFactory extends Factory
{
    protected $model = SmsTemplate::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'code' => fake()->unique()->word(),
            'body' => 'Bonjour {{guardian_name}}, '.fake()->sentence(),
            'is_active' => true,
        ];
    }
}
