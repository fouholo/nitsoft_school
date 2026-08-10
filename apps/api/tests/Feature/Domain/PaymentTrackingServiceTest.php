<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\PaymentTrackingService;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;

test('un élève avec une facture échue impayée et une facture future payée par anticipation est en retard', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'amount_due' => 500,
        'amount_paid' => 0,
        'due_date' => now()->subMonth(),
        'status' => 'pending',
    ]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'amount_due' => 300,
        'amount_paid' => 200,
        'due_date' => now()->addMonth(),
        'status' => 'partially_paid',
    ]);

    $balance = (new PaymentTrackingService)->balanceForStudent($student->id, $schoolYear->id);

    expect($balance['due_so_far'])->toBe(500.0)
        ->and($balance['total_paid'])->toBe(200.0)
        ->and($balance['balance'])->toBe(300.0);
});

test('un élève ayant soldé sa facture échue et avancé sur une facture future est en avance', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'amount_due' => 500,
        'amount_paid' => 500,
        'due_date' => now()->subMonth(),
        'status' => 'paid',
    ]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'amount_due' => 200,
        'amount_paid' => 100,
        'due_date' => now()->addMonth(),
        'status' => 'partially_paid',
    ]);

    $balance = (new PaymentTrackingService)->balanceForStudent($student->id, $schoolYear->id);

    expect($balance['balance'])->toBe(-100.0);
});

test('une facture annulée n’entre dans aucune des deux sommes', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'amount_due' => 999,
        'amount_paid' => 999,
        'due_date' => now()->subMonth(),
        'status' => 'cancelled',
    ]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'amount_due' => 100,
        'amount_paid' => 0,
        'due_date' => now()->subMonth(),
        'status' => 'pending',
    ]);

    $balance = (new PaymentTrackingService)->balanceForStudent($student->id, $schoolYear->id);

    expect($balance['due_so_far'])->toBe(100.0)
        ->and($balance['total_paid'])->toBe(0.0);
});

test('ownerId ne compte que les factures créées par cet utilisateur', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $userA = createUserWithRole($establishment, 'educateur');
    $userB = createUserWithRole($establishment, 'educateur');

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'amount_due' => 100,
        'amount_paid' => 0,
        'due_date' => now()->subMonth(),
        'created_by' => $userA->id,
    ]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'amount_due' => 900,
        'amount_paid' => 0,
        'due_date' => now()->subMonth(),
        'created_by' => $userB->id,
    ]);

    $balance = (new PaymentTrackingService)->balanceForStudent($student->id, $schoolYear->id, $userA->id);

    expect($balance['due_so_far'])->toBe(100.0);
});
