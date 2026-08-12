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

    $html = view('pdf.receipt', ['payment' => $payment])->render();

    expect(substr_count($html, '<img'))->toBe(2)
        ->and($html)->toContain($payment->uid_serveur);
});

test('le code-barres est absent tant que le paiement n’est pas synchronisé (uid_serveur absent)', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $payment = makePaymentIn($establishment);
    $payment->uid_serveur = null;

    $html = view('pdf.receipt', ['payment' => $payment])->render();

    expect(substr_count($html, '<img'))->toBe(1);
});
