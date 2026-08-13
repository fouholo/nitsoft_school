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
            $invoice->amount_paid = round((float) $invoice->amount_paid + (float) $data['amount'], 2);
            $invoice->status = $this->statusFor($invoice);
            $invoice->save();

            $snapshot = $this->tuitionSnapshotFor($invoice);

            return $invoice->payments()->create([
                'establishment_id' => $invoice->establishment_id,
                'student_id' => $invoice->student_id,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'paid_at' => $data['paid_at'],
                'reference' => $data['reference'] ?? null,
                'received_by' => $receivedBy->id,
                ...$snapshot,
            ]);
        });
    }

    private function statusFor(Invoice $invoice): string
    {
        if ($invoice->amount_paid >= $invoice->amount_due) {
            return 'paid';
        }

        return $invoice->amount_paid > 0 ? 'partially_paid' : 'pending';
    }

    /**
     * Instantané de la situation financière (scolarité, hors inscription) de
     * l'élève au moment de ce paiement — figé sur le Payment pour que le
     * reçu reste historiquement exact même après de futurs paiements.
     *
     * @return array{tuition_paid_total: float, tuition_remaining: float, next_installment_due_date: ?\Carbon\Carbon, next_installment_amount: ?float}
     */
    private function tuitionSnapshotFor(Invoice $invoice): array
    {
        $tuitionInvoices = Invoice::where('student_id', $invoice->student_id)
            ->where('school_year_id', $invoice->school_year_id)
            ->whereNotNull('installment_id')
            ->where('status', '!=', 'cancelled');

        $tuitionPaidTotal = (float) (clone $tuitionInvoices)->sum('amount_paid');
        $tuitionDueTotal = (float) (clone $tuitionInvoices)->sum('amount_due');

        $nextInstallmentDueDate = Invoice::nextDueDateAfterCumulativePayments($tuitionInvoices, $tuitionPaidTotal);

        $nextInstallmentAmount = $nextInstallmentDueDate
            ? (float) (clone $tuitionInvoices)->where('due_date', '<=', $nextInstallmentDueDate)->sum('amount_due') - $tuitionPaidTotal
            : null;

        return [
            'tuition_paid_total' => $tuitionPaidTotal,
            'tuition_remaining' => $tuitionDueTotal - $tuitionPaidTotal,
            'next_installment_due_date' => $nextInstallmentDueDate,
            'next_installment_amount' => $nextInstallmentAmount,
        ];
    }
}
