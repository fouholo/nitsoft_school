<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Payment;
use App\Domain\Enrollment\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre un paiement et met à jour le total versé de l'inscription dans
 * une seule transaction. `Payment` reste la source de vérité (table à
 * écriture additive, sûre en cas de synchronisation hors-ligne
 * concurrente) — `Enrollment.total_paid` est un cache toujours **recalculé**
 * (jamais incrémenté) à partir de la somme des paiements existants, ce qui
 * le rend auto-cicatrisant après une éventuelle divergence de
 * synchronisation. Le paiement fait lui-même office de reçu numéroté
 * (uid_local/uid_serveur via Syncable).
 */
class PaymentService
{
    /**
     * @param  array{amount: float, method: string, paid_at: string, reference: ?string}  $data
     */
    public function recordPayment(Enrollment $enrollment, array $data, User $receivedBy): Payment
    {
        return DB::transaction(function () use ($enrollment, $data, $receivedBy) {
            $snapshot = $this->tuitionSnapshotFor($enrollment, (float) $data['amount']);

            $payment = $enrollment->payments()->create([
                'establishment_id' => $enrollment->establishment_id,
                'student_id' => $enrollment->student_id,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'paid_at' => $data['paid_at'],
                'reference' => $data['reference'] ?? null,
                'received_by' => $receivedBy->id,
                ...$snapshot,
            ]);

            $enrollment->total_paid = (float) $enrollment->payments()->sum('amount');
            $enrollment->save();

            return $payment;
        });
    }

    /**
     * Instantané de la situation financière (scolarité, hors inscription) de
     * l'élève au moment de ce paiement — figé sur le Payment pour que le
     * reçu reste historiquement exact même après de futurs paiements.
     *
     * Un paiement n'étant plus rattaché à un poste précis ("montant libre"),
     * les versements sont imputés par convention d'abord aux frais
     * d'inscription (dus dès l'inscription), puis aux tranches de scolarité
     * dans l'ordre de leurs échéances.
     *
     * @return array{tuition_paid_total: float, tuition_remaining: float, next_installment_due_date: ?\Carbon\Carbon, next_installment_amount: ?float}
     */
    private function tuitionSnapshotFor(Enrollment $enrollment, float $paymentAmount): array
    {
        $registrationAmount = (float) ($enrollment->registration_amount ?? 0);
        $installments = $enrollment->tuitionInstallments();
        $tuitionDueTotal = (float) $installments->sum('amount');

        $totalPaid = (float) $enrollment->total_paid + $paymentAmount;
        $paidTowardTuition = max(0.0, min($tuitionDueTotal, $totalPaid - $registrationAmount));

        $cumulativeDue = 0.0;
        $nextInstallmentDueDate = null;
        $nextInstallmentAmount = null;

        foreach ($installments as $installment) {
            $cumulativeDue += $installment['amount'];

            if ($cumulativeDue > $paidTowardTuition) {
                $nextInstallmentDueDate = $installment['due_date'];
                $nextInstallmentAmount = $cumulativeDue - $paidTowardTuition;

                break;
            }
        }

        return [
            'tuition_paid_total' => $paidTowardTuition,
            'tuition_remaining' => $tuitionDueTotal - $paidTowardTuition,
            'next_installment_due_date' => $nextInstallmentDueDate,
            'next_installment_amount' => $nextInstallmentAmount,
        ];
    }
}
