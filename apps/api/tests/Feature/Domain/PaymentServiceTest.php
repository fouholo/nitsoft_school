<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Services\PaymentService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;

function enrollmentIn(Establishment $establishment, array $overrides = []): Enrollment
{
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    return Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        ...$overrides,
    ]);
}

test('un paiement met à jour le total versé de l’inscription et génère un reçu', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');

    $enrollment = enrollmentIn($establishment, ['registration_amount' => 100]);

    $payment = (new PaymentService)->recordPayment($enrollment, [
        'amount' => 40,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
        'reference' => null,
    ], $accountant);

    $enrollment->refresh();

    expect((float) $enrollment->total_paid)->toBe(40.0)
        ->and($payment->uid_local)->not->toBeNull()
        ->and($payment->uid_serveur)->not->toBeNull()
        ->and($payment->receiptNumber())->toBe($payment->uid_serveur);
});

test('un second paiement additionne au total déjà versé', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');

    $enrollment = enrollmentIn($establishment, ['registration_amount' => 100]);

    $service = new PaymentService;
    $service->recordPayment($enrollment, ['amount' => 40, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);
    $service->recordPayment($enrollment, ['amount' => 60, 'method' => 'mobile_money', 'paid_at' => now()->toDateString(), 'reference' => 'MM-123'], $accountant);

    $enrollment->refresh();

    expect((float) $enrollment->total_paid)->toBe(100.0);
});

test('le total versé est recalculé (jamais incrémenté) à partir de la somme des paiements', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');

    $enrollment = enrollmentIn($establishment, ['registration_amount' => 100]);

    // Un total_paid volontairement désynchronisé (ex: après une divergence
    // de synchronisation hors-ligne) doit être corrigé, pas aggravé, par le
    // prochain paiement.
    $enrollment->update(['total_paid' => 999]);

    (new PaymentService)->recordPayment($enrollment, ['amount' => 10, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);

    $enrollment->refresh();

    expect((float) $enrollment->total_paid)->toBe(10.0);
});

test('le numéro de reçu (uid_serveur) est attribué de façon séquentielle', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');
    $service = new PaymentService;

    $enrollmentA = enrollmentIn($establishment, ['registration_amount' => 100]);
    $enrollmentB = enrollmentIn($establishment, ['registration_amount' => 100]);

    $paymentA = $service->recordPayment($enrollmentA, ['amount' => 10, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);
    $paymentB = $service->recordPayment($enrollmentB, ['amount' => 10, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);

    $sequenceA = (int) substr($paymentA->uid_serveur, 3);
    $sequenceB = (int) substr($paymentB->uid_serveur, 3);

    expect($paymentA->uid_serveur)->toStartWith('241')
        ->and($sequenceB)->toBe($sequenceA + 1);
});

test('l’instantané financier reste figé sur un paiement même après un paiement ultérieur', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $schoolYear = SchoolYear::factory()->create();

    $installment1 = Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche 1',
        'due_date' => now()->addDays(10),
        'position' => 1,
    ]);
    $installment2 = Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche 2',
        'due_date' => now()->addDays(40),
        'position' => 2,
    ]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 0,
        'installment_1_amount' => 100,
        'installment_2_amount' => 150,
    ]);

    $service = new PaymentService;

    $payment1 = $service->recordPayment($enrollment, ['amount' => 100, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);

    expect((float) $payment1->tuition_paid_total)->toBe(100.0)
        ->and((float) $payment1->tuition_remaining)->toBe(150.0)
        ->and($payment1->next_installment_due_date?->isSameDay($installment2->due_date))->toBeTrue()
        ->and((float) $payment1->next_installment_amount)->toBe(150.0);

    $service->recordPayment($enrollment, ['amount' => 150, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);

    $payment1->refresh();

    expect((float) $payment1->tuition_paid_total)->toBe(100.0)
        ->and((float) $payment1->tuition_remaining)->toBe(150.0)
        ->and($payment1->next_installment_due_date?->isSameDay($installment2->due_date))->toBeTrue()
        ->and((float) $payment1->next_installment_amount)->toBe(150.0);
});

test('un élève soldé n’a pas de prochain versement dans l’instantané', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $schoolYear = SchoolYear::factory()->create();

    $installment = Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche unique',
        'due_date' => now()->addDays(10),
        'position' => 1,
    ]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 0,
        'installment_1_amount' => 100,
    ]);

    $payment = (new PaymentService)->recordPayment($enrollment, ['amount' => 100, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);

    expect((float) $payment->tuition_paid_total)->toBe(100.0)
        ->and((float) $payment->tuition_remaining)->toBe(0.0)
        ->and($payment->next_installment_due_date)->toBeNull()
        ->and($payment->next_installment_amount)->toBeNull();
});

test('un paiement qui couvre uniquement les frais d’inscription n’inflate pas le total scolarité de l’instantané', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $schoolYear = SchoolYear::factory()->create();

    $installment = Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche 1',
        'due_date' => now()->addDays(10),
        'position' => 1,
    ]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 50,
        'installment_1_amount' => 200,
    ]);

    // Les versements couvrent d'abord les frais d'inscription, par
    // convention (aucune allocation explicite du caissier) — ce paiement
    // couvre exactement l'inscription, rien ne doit apparaître côté
    // scolarité.
    $payment = (new PaymentService)->recordPayment($enrollment, ['amount' => 50, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);

    expect((float) $payment->tuition_paid_total)->toBe(0.0)
        ->and((float) $payment->tuition_remaining)->toBe(200.0)
        ->and($payment->next_installment_due_date?->isSameDay($installment->due_date))->toBeTrue()
        ->and((float) $payment->next_installment_amount)->toBe(200.0);
});
