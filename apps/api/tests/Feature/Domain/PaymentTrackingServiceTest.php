<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\PaymentTrackingService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;

test('un élève avec une tranche échue impayée et une tranche future payée par anticipation est en retard', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche échue',
        'due_date' => now()->subMonth(),
        'position' => 1,
    ]);
    Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche future',
        'due_date' => now()->addMonth(),
        'position' => 2,
    ]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 0,
        'installment_1_amount' => 500,
        'installment_2_amount' => 300,
        'total_paid' => 200,
    ]);

    $balance = (new PaymentTrackingService)->balanceForStudent($student->id, $schoolYear->id);

    expect($balance['due_so_far'])->toBe(500.0)
        ->and($balance['total_paid'])->toBe(200.0)
        ->and($balance['balance'])->toBe(300.0);
});

test('un élève ayant soldé sa tranche échue et avancé sur une tranche future est en avance', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche échue',
        'due_date' => now()->subMonth(),
        'position' => 1,
    ]);
    Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche future',
        'due_date' => now()->addMonth(),
        'position' => 2,
    ]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 0,
        'installment_1_amount' => 500,
        'installment_2_amount' => 200,
        'total_paid' => 600,
    ]);

    $balance = (new PaymentTrackingService)->balanceForStudent($student->id, $schoolYear->id);

    expect($balance['balance'])->toBe(-100.0);
});

test('une tranche non configurée (null) n’entre pas dans le dû', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche échue',
        'due_date' => now()->subMonth(),
        'position' => 1,
    ]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 100,
        'installment_1_amount' => null,
    ]);

    $balance = (new PaymentTrackingService)->balanceForStudent($student->id, $schoolYear->id);

    expect($balance['due_so_far'])->toBe(100.0)
        ->and($balance['total_paid'])->toBe(0.0);
});

test('ownerId ne compte que les inscriptions sur lesquelles cet utilisateur a personnellement encaissé un paiement', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $userA = createUserWithRole($establishment, 'educateur');
    $userB = createUserWithRole($establishment, 'educateur');

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $studentA = Student::factory()->create(['establishment_id' => $establishment->id]);
    $studentB = Student::factory()->create(['establishment_id' => $establishment->id]);

    $enrollmentA = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $studentA->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 100,
    ]);
    $enrollmentB = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $studentB->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 900,
    ]);

    Payment::factory()->create([
        'establishment_id' => $establishment->id,
        'enrollment_id' => $enrollmentA->id,
        'student_id' => $studentA->id,
        'received_by' => $userA->id,
    ]);
    Payment::factory()->create([
        'establishment_id' => $establishment->id,
        'enrollment_id' => $enrollmentB->id,
        'student_id' => $studentB->id,
        'received_by' => $userB->id,
    ]);

    $balances = (new PaymentTrackingService)->balances($schoolYear->id, $userA->id);

    expect($balances)->toHaveCount(1)
        ->and($balances->first()['student_id'])->toBe($studentA->id);
});
