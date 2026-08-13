<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\PaymentService;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;

test('un paiement partiel passe la facture en partially_paid et génère un reçu', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');

    $invoice = Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'amount_due' => 100,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $payment = (new PaymentService)->recordPayment($invoice, [
        'amount' => 40,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
        'reference' => null,
    ], $accountant);

    $invoice->refresh();

    expect((float) $invoice->amount_paid)->toBe(40.0)
        ->and($invoice->status)->toBe('partially_paid')
        ->and($payment->uid_local)->not->toBeNull()
        ->and($payment->uid_serveur)->not->toBeNull()
        ->and($payment->receiptNumber())->toBe($payment->uid_serveur);
});

test('un paiement qui couvre le solde restant passe la facture à paid', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');

    $invoice = Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'amount_due' => 100,
        'amount_paid' => 40,
        'status' => 'partially_paid',
    ]);

    (new PaymentService)->recordPayment($invoice, [
        'amount' => 60,
        'method' => 'mobile_money',
        'paid_at' => now()->toDateString(),
        'reference' => 'MM-123',
    ], $accountant);

    $invoice->refresh();

    expect((float) $invoice->amount_paid)->toBe(100.0)
        ->and($invoice->status)->toBe('paid');
});

test('le numéro de reçu (uid_serveur) est attribué de façon séquentielle', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');
    $service = new PaymentService;

    $invoiceA = Invoice::factory()->create(['establishment_id' => $establishment->id, 'amount_due' => 100]);
    $invoiceB = Invoice::factory()->create(['establishment_id' => $establishment->id, 'amount_due' => 100]);

    $paymentA = $service->recordPayment($invoiceA, ['amount' => 10, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);
    $paymentB = $service->recordPayment($invoiceB, ['amount' => 10, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);

    $sequenceA = (int) substr($paymentA->uid_serveur, 3);
    $sequenceB = (int) substr($paymentB->uid_serveur, 3);

    expect($paymentA->uid_serveur)->toStartWith('241')
        ->and($sequenceB)->toBe($sequenceA + 1);
});

test('l’instantané financier reste figé sur un paiement même après un paiement ultérieur', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);

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

    $invoice1 = Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'installment_id' => $installment1->id,
        'amount_due' => 100,
        'amount_paid' => 0,
        'due_date' => $installment1->due_date,
    ]);
    $invoice2 = Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'installment_id' => $installment2->id,
        'amount_due' => 150,
        'amount_paid' => 0,
        'due_date' => $installment2->due_date,
    ]);

    $service = new PaymentService;

    $payment1 = $service->recordPayment($invoice1, ['amount' => 100, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);

    expect((float) $payment1->tuition_paid_total)->toBe(100.0)
        ->and((float) $payment1->tuition_remaining)->toBe(150.0)
        ->and($payment1->next_installment_due_date?->isSameDay($installment2->due_date))->toBeTrue()
        ->and((float) $payment1->next_installment_amount)->toBe(150.0);

    $service->recordPayment($invoice2, ['amount' => 150, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);

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
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);

    $installment = Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche unique',
        'due_date' => now()->addDays(10),
        'position' => 1,
    ]);

    $invoice = Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'installment_id' => $installment->id,
        'amount_due' => 100,
        'amount_paid' => 0,
        'due_date' => $installment->due_date,
    ]);

    $payment = (new PaymentService)->recordPayment($invoice, ['amount' => 100, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);

    expect((float) $payment->tuition_paid_total)->toBe(100.0)
        ->and((float) $payment->tuition_remaining)->toBe(0.0)
        ->and($payment->next_installment_due_date)->toBeNull()
        ->and($payment->next_installment_amount)->toBeNull();
});

test('le paiement de la facture d’inscription reflète l’instantané de la scolarité, pas de l’inscription', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);

    $installment = Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche 1',
        'due_date' => now()->addDays(10),
        'position' => 1,
    ]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'installment_id' => $installment->id,
        'amount_due' => 200,
        'amount_paid' => 100,
        'due_date' => $installment->due_date,
    ]);

    $registrationInvoice = Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'installment_id' => null,
        'label' => "Frais d'inscription",
        'amount_due' => 50,
        'amount_paid' => 0,
        'due_date' => now()->addDay(),
    ]);

    $payment = (new PaymentService)->recordPayment($registrationInvoice, ['amount' => 50, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);

    expect((float) $payment->tuition_paid_total)->toBe(100.0)
        ->and((float) $payment->tuition_remaining)->toBe(100.0)
        ->and($payment->next_installment_due_date?->isSameDay($installment->due_date))->toBeTrue()
        ->and((float) $payment->next_installment_amount)->toBe(100.0);
});
