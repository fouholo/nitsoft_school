<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\PaymentService;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;

function makePaymentIn(Establishment $establishment): Payment
{
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $invoice = Invoice::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $student->id]);
    $accountant = createUserWithRole($establishment, 'caissier');

    return (new PaymentService)->recordPayment($invoice, [
        'amount' => 25,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
        'reference' => null,
    ], $accountant);
}

test('un caissier peut consulter le reçu PDF d’un paiement en ligne', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');

    actingInEstablishment($establishment);
    $payment = makePaymentIn($establishment);

    $response = $this->actingAs($accountant)->get(route('billing.payments.receipt', $payment));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

test('le paramètre download force le téléchargement du reçu', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');

    actingInEstablishment($establishment);
    $payment = makePaymentIn($establishment);

    $response = $this->actingAs($accountant)->get(route('billing.payments.receipt', ['payment' => $payment, 'download' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('un enseignant ne peut pas consulter le reçu (réservé aux gestionnaires facturation)', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    actingInEstablishment($establishment);
    $payment = makePaymentIn($establishment);

    $response = $this->actingAs($teacher)->get(route('billing.payments.receipt', $payment));

    $response->assertForbidden();
});

test('un membre d’un autre établissement ne peut même pas résoudre le paiement (isolation tenant)', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();
    $adminB = createUserWithRole($establishmentB, 'directeur');

    actingInEstablishment($establishmentA);
    $payment = makePaymentIn($establishmentA);

    actingInEstablishment($establishmentB);

    $response = $this->actingAs($adminB)->get(route('billing.payments.receipt', $payment));

    $response->assertNotFound();
});

test('le reçu contient un QR code (uid_local) et un code-barres (uid_serveur) une fois synchronisé', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $payment = makePaymentIn($establishment);

    expect($payment->uid_serveur)->not->toBeNull();

    $html = view('pdf.receipt', ['payment' => $payment, 'totalTuition' => 0.0, 'totalPayments' => 0.0, 'nextPaymentDueDate' => null])->render();

    expect(substr_count($html, '<img'))->toBe(2)
        ->and($html)->toContain($payment->uid_serveur);
});

test('le code-barres est absent tant que le paiement n’est pas synchronisé (uid_serveur absent)', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $payment = makePaymentIn($establishment);
    $payment->uid_serveur = null;

    $html = view('pdf.receipt', ['payment' => $payment, 'totalTuition' => 0.0, 'totalPayments' => 0.0, 'nextPaymentDueDate' => null])->render();

    expect(substr_count($html, '<img'))->toBe(1);
});

test('le reçu affiche le total scolarité, le total versement, le reste scolarité et le cachet de l’établissement', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $payment = makePaymentIn($establishment);

    $html = view('pdf.receipt', ['payment' => $payment, 'totalTuition' => 100.0, 'totalPayments' => 25.0, 'nextPaymentDueDate' => null])->render();

    expect($html)->toContain('Total scolarité')
        ->and($html)->toContain('Total versement')
        ->and($html)->toContain('Reste scolarité')
        ->and($html)->toContain(money(75.0))
        ->and($html)->toContain("Cachet de l'établissement")
        ->and($html)->not->toContain('Date du prochain paiement');
});

test('la date du prochain paiement s’affiche quand elle est fournie', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $payment = makePaymentIn($establishment);

    $html = view('pdf.receipt', ['payment' => $payment, 'totalTuition' => 0.0, 'totalPayments' => 0.0, 'nextPaymentDueDate' => \Carbon\Carbon::parse('2026-12-01')])->render();

    expect($html)->toContain('Date du prochain paiement')
        ->and($html)->toContain('01/12/2026');
});

test('le total scolarité et le total versement excluent la facture d’inscription (installment_id null)', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $schoolYear = \App\Domain\Academics\Models\SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $installment = \App\Domain\Billing\Models\Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche 1',
        'due_date' => now()->addMonth(),
        'position' => 1,
    ]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'installment_id' => null,
        'label' => "Frais d'inscription",
        'amount_due' => 50,
        'amount_paid' => 50,
    ]);

    $tuitionInvoice = Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'installment_id' => $installment->id,
        'label' => 'Tranche 1',
        'amount_due' => 200,
        'amount_paid' => 0,
    ]);

    $accountant = createUserWithRole($establishment, 'caissier');
    $payment = (new PaymentService)->recordPayment($tuitionInvoice, [
        'amount' => 100,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
        'reference' => null,
    ], $accountant);

    $tuitionInvoices = Invoice::where('student_id', $student->id)
        ->where('school_year_id', $schoolYear->id)
        ->whereNotNull('installment_id')
        ->where('status', '!=', 'cancelled');

    $totalTuition = (float) (clone $tuitionInvoices)->sum('amount_due');
    $totalPayments = (float) (clone $tuitionInvoices)->sum('amount_paid');

    expect($totalTuition)->toBe(200.0)
        ->and($totalPayments)->toBe(100.0);

    $html = view('pdf.receipt', ['payment' => $payment, 'totalTuition' => $totalTuition, 'totalPayments' => $totalPayments, 'nextPaymentDueDate' => null])->render();

    expect($html)->toContain(money(200.0))
        ->and($html)->toContain(money(100.0))
        ->and($html)->not->toContain(money(250.0));
});

test('la date du prochain paiement est celle de la première tranche dont la somme cumulée des factures dépasse le total déjà versé', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $schoolYear = \App\Domain\Academics\Models\SchoolYear::factory()->create(['establishment_id' => $establishment->id]);

    $installment1 = \App\Domain\Billing\Models\Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche 1',
        'due_date' => now()->addDays(10),
        'position' => 1,
    ]);
    $installment2 = \App\Domain\Billing\Models\Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche 2',
        'due_date' => now()->addDays(40),
        'position' => 2,
    ]);
    $installment3 = \App\Domain\Billing\Models\Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche 3',
        'due_date' => now()->addDays(70),
        'position' => 3,
    ]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'installment_id' => null,
        'label' => "Frais d'inscription",
        'amount_due' => 50,
        'amount_paid' => 50,
        'due_date' => now()->addDay(),
    ]);

    $invoice1 = Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'installment_id' => $installment1->id,
        'label' => 'Tranche 1',
        'amount_due' => 100,
        'amount_paid' => 100,
        'due_date' => $installment1->due_date,
        'status' => 'paid',
    ]);
    $invoice2 = Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'installment_id' => $installment2->id,
        'label' => 'Tranche 2',
        'amount_due' => 150,
        'amount_paid' => 0,
        'due_date' => $installment2->due_date,
    ]);
    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'installment_id' => $installment3->id,
        'label' => 'Tranche 3',
        'amount_due' => 200,
        'amount_paid' => 0,
        'due_date' => $installment3->due_date,
    ]);

    $accountant = createUserWithRole($establishment, 'caissier');
    $payment = (new PaymentService)->recordPayment($invoice1, [
        'amount' => 100,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
        'reference' => null,
    ], $accountant);

    $response = $this->actingAs($accountant)->get(route('billing.payments.receipt', $payment));

    $response->assertOk();

    $tuitionInvoices = Invoice::where('student_id', $student->id)
        ->where('school_year_id', $schoolYear->id)
        ->whereNotNull('installment_id')
        ->where('status', '!=', 'cancelled');

    $totalPayments = (float) (clone $tuitionInvoices)->sum('amount_paid');

    $nextPaymentDueDate = Invoice::nextDueDateAfterCumulativePayments($tuitionInvoices, $totalPayments);

    expect($nextPaymentDueDate?->isSameDay($invoice2->due_date))->toBeTrue();
});
