<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'invoice_id' => Invoice::factory(),
            'student_id' => Student::factory(),
            'amount' => 10000,
            'method' => 'cash',
            'paid_at' => now(),
            'received_by' => User::factory(),
        ];
    }
}
