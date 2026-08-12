<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre un paiement et met à jour le statut de la facture dans une
 * seule transaction — voir plan d'architecture, section 9
 * (idempotence/cohérence de la facturation). Le paiement fait lui-même
 * office de reçu numéroté (uid_local/uid_serveur via Syncable).
 */
class PaymentService
{
    /**
     * @param  array{amount: float, method: string, paid_at: string, reference: ?string}  $data
     */
    public function recordPayment(Invoice $invoice, array $data, User $receivedBy): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $receivedBy) {
            $payment = $invoice->payments()->create([
                'establishment_id' => $invoice->establishment_id,
                'student_id' => $invoice->student_id,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'paid_at' => $data['paid_at'],
                'reference' => $data['reference'] ?? null,
                'received_by' => $receivedBy->id,
            ]);

            $invoice->amount_paid = round((float) $invoice->amount_paid + (float) $data['amount'], 2);
            $invoice->status = $this->statusFor($invoice);
            $invoice->save();

            return $payment;
        });
    }

    private function statusFor(Invoice $invoice): string
    {
        if ($invoice->amount_paid >= $invoice->amount_due) {
            return 'paid';
        }

        return $invoice->amount_paid > 0 ? 'partially_paid' : 'pending';
    }
}
