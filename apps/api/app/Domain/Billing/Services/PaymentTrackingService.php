<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Enrollment\Models\Enrollment;
use Illuminate\Support\Collection;

/**
 * Calcule, à la volée, le solde retard/avance de paiement d'un élève sur une
 * année scolaire — jamais stocké ni recalculé par une tâche planifiée. Voir
 * docs/superpowers/specs/2026-08-10-suivi-retards-avances-paiement-design.md
 * et docs/superpowers/specs/2026-08-18-refonte-factures-scolarite-design.md.
 */
final class PaymentTrackingService
{
    /**
     * @return Collection<int, array{student_id: int<0, max>, due_so_far: float, total_paid: float, balance: float}>
     */
    public function balances(int $schoolYearId, ?int $ownerId = null, ?int $studentId = null): Collection
    {
        return Enrollment::query()
            ->where('school_year_id', $schoolYearId)
            ->where('status', 'active')
            // finance.scope_own_only (éducateur) : sans Invoice.created_by,
            // la portée "mes propres saisies" s'appuie désormais sur les
            // paiements personnellement encaissés (Payment.received_by).
            ->when($ownerId, fn ($query) => $query->whereHas(
                'payments',
                fn ($paymentsQuery) => $paymentsQuery->where('received_by', $ownerId),
            ))
            ->when($studentId, fn ($query) => $query->where('student_id', $studentId))
            ->get()
            ->map(function (Enrollment $enrollment): array {
                $dueSoFar = (float) ($enrollment->registration_amount ?? 0)
                    + (float) $enrollment->tuitionInstallmentsDue()->sum('amount');
                $totalPaid = (float) $enrollment->total_paid;

                return [
                    'student_id' => $enrollment->student_id,
                    'due_so_far' => $dueSoFar,
                    'total_paid' => $totalPaid,
                    'balance' => $dueSoFar - $totalPaid,
                ];
            });
    }

    /**
     * @return array{student_id: int<0, max>, due_so_far: float, total_paid: float, balance: float}|null
     */
    public function balanceForStudent(int $studentId, int $schoolYearId, ?int $ownerId = null): ?array
    {
        return $this->balances($schoolYearId, $ownerId, $studentId)->first();
    }
}
