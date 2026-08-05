<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'student_id' => Student::factory(),
            'school_year_id' => SchoolYear::factory(),
            'label' => 'Frais de scolarité',
            'amount_due' => 50000,
            'amount_paid' => 0,
            'status' => 'pending',
            'due_date' => now()->addMonth()->toDateString(),
        ];
    }
}
