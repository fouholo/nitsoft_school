<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Models\Expense;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'label' => 'Fournitures de bureau',
            'amount' => 15000,
            'spent_at' => now()->toDateString(),
            'recorded_by' => User::factory(),
        ];
    }
}
