<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\PaymentService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;

function makePaymentIn(Establishment $establishment): Payment
{
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $enrollment = Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $student->id, 'registration_amount' => 100]);
    $accountant = createUserWithRole($establishment, 'caissier');

    return (new PaymentService)->recordPayment($enrollment, [
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

test('le reçu affiche le moyen de paiement en français plutôt que le code technique', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $payment = makePaymentIn($establishment);
    $payment->method = 'mobile_money';

    $html = view('pdf.receipt', ['payment' => $payment])->render();

    expect($html)->toContain('Mobile Money')
        ->and($html)->not->toContain('mobile_money');
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

test('le reçu affiche les lignes inscription et scolarité (montant/versée/reste) et le cachet de l’établissement', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $payment = makePaymentIn($establishment);
    $payment->registration_paid = 25.0;
    $payment->registration_remaining = 0.0;
    $payment->tuition_paid_total = 25.0;
    $payment->tuition_remaining = 75.0;

    $html = view('pdf.receipt', ['payment' => $payment])->render();

    expect($html)->toContain('Inscription :')
        ->and($html)->toContain('Scolarité :')
        ->and($html)->toContain('Versée :')
        ->and($html)->toContain('Reste :')
        ->and($html)->toContain(money(75.0))
        ->and($html)->toContain("Cachet de l'établissement")
        ->and($html)->not->toContain('Prochain paiement');
});

test('le bloc situation financière est absent quand le paiement n’a pas d’instantané (ancien paiement)', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $payment = makePaymentIn($establishment);
    $payment->tuition_paid_total = null;
    $payment->tuition_remaining = null;

    $html = view('pdf.receipt', ['payment' => $payment])->render();

    expect($html)->not->toContain('Inscription :')
        ->and($html)->not->toContain('Scolarité :')
        ->and($html)->not->toContain('Prochain paiement')
        ->and($html)->toContain("Cachet de l'établissement");
});

test('la ligne "Prochain paiement" (montant et date) s’affiche quand elle est fournie', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $payment = makePaymentIn($establishment);
    $payment->tuition_paid_total = 0.0;
    $payment->tuition_remaining = 0.0;
    $payment->next_installment_due_date = \Carbon\Carbon::parse('2026-12-01');
    $payment->next_installment_amount = 150.0;

    $html = view('pdf.receipt', ['payment' => $payment])->render();

    expect($html)->toContain('Prochain paiement :')
        ->and($html)->toContain('Montant :')
        ->and($html)->toContain('Date :')
        ->and($html)->toContain('01/12/2026')
        ->and($html)->toContain(money(150.0));
});

test('l’instantané du paiement exclut les frais d’inscription déjà couverts', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    Installment::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'label' => 'Tranche 1',
        'due_date' => now()->addMonth(),
        'position' => 1,
    ]);

    // Frais d'inscription (50) déjà couverts par un versement antérieur.
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 50,
        'installment_1_amount' => 200,
        'total_paid' => 50,
    ]);

    $accountant = createUserWithRole($establishment, 'caissier');
    $payment = (new PaymentService)->recordPayment($enrollment, [
        'amount' => 100,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
        'reference' => null,
    ], $accountant);

    expect((float) $payment->registration_paid)->toBe(50.0)
        ->and((float) $payment->registration_remaining)->toBe(0.0)
        ->and((float) $payment->tuition_paid_total)->toBe(100.0)
        ->and((float) $payment->tuition_remaining)->toBe(100.0);

    $html = view('pdf.receipt', ['payment' => $payment])->render();

    expect($html)->toContain(money(200.0))
        ->and($html)->toContain(money(100.0))
        ->and($html)->not->toContain(money(250.0));
});

test('la date et la somme du prochain versement affichées sur le reçu correspondent au cumul des tranches dont la somme dépasse le total déjà versé', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);

    Installment::create([
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

    $accountant = createUserWithRole($establishment, 'caissier');
    $payment = (new PaymentService)->recordPayment($enrollment, [
        'amount' => 100,
        'method' => 'cash',
        'paid_at' => now()->toDateString(),
        'reference' => null,
    ], $accountant);

    $response = $this->actingAs($accountant)->get(route('billing.payments.receipt', $payment));

    $response->assertOk();

    $html = view('pdf.receipt', ['payment' => $payment])->render();

    expect($html)->toContain($installment2->due_date->format('d/m/Y'))
        ->and($html)->toContain(money(150.0));
});
