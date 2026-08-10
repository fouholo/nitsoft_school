<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Discount;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discount>
 */
class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'student_id' => Student::factory(),
            'school_year_id' => SchoolYear::factory(),
            'type' => 'percentage',
            'value' => 10,
            'created_by' => User::factory(),
        ];
    }
}
