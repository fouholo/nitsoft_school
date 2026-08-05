<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\PaymentService;
use App\Domain\Establishments\Models\Establishment;

test('un paiement partiel passe la facture en partially_paid et génère un reçu', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'accountant');

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
        ->and($payment->receipt)->not->toBeNull()
        ->and($payment->receipt->receipt_number)->toBe('REC-000001');
});

test('un paiement qui couvre le solde restant passe la facture à paid', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'accountant');

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

test('les numéros de reçus sont séquentiels par établissement', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'accountant');
    $service = new PaymentService;

    $invoiceA = Invoice::factory()->create(['establishment_id' => $establishment->id, 'amount_due' => 100]);
    $invoiceB = Invoice::factory()->create(['establishment_id' => $establishment->id, 'amount_due' => 100]);

    $paymentA = $service->recordPayment($invoiceA, ['amount' => 10, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);
    $paymentB = $service->recordPayment($invoiceB, ['amount' => 10, 'method' => 'cash', 'paid_at' => now()->toDateString(), 'reference' => null], $accountant);

    expect($paymentA->receipt->receipt_number)->toBe('REC-000001')
        ->and($paymentB->receipt->receipt_number)->toBe('REC-000002');
});
