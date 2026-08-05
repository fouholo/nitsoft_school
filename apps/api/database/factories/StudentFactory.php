<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birth_date' => fake()->date(),
            'gender' => fake()->randomElement(['m', 'f']),
            'student_number' => fake()->unique()->numerify('MAT-####'),
            'is_active' => true,
        ];
    }
}
