<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Calcule, à la volée, le solde retard/avance de paiement d'un élève sur une
 * année scolaire — jamais stocké ni recalculé par une tâche planifiée. Voir
 * docs/superpowers/specs/2026-08-10-suivi-retards-avances-paiement-design.md.
 */
final class PaymentTrackingService
{
    /**
     * @return Collection<int, array{student_id: int, due_so_far: float, total_paid: float, balance: float}>
     */
    public function balances(int $schoolYearId, ?int $ownerId = null, ?int $studentId = null): Collection
    {
        return Invoice::query()
            ->where('school_year_id', $schoolYearId)
            ->where('status', '!=', 'cancelled')
            ->when($ownerId, fn ($query) => $query->where('created_by', $ownerId))
            ->when($studentId, fn ($query) => $query->where('student_id', $studentId))
            ->selectRaw('student_id, SUM(CASE WHEN due_date <= ? THEN amount_due ELSE 0 END) as due_so_far, SUM(amount_paid) as total_paid', [now()->toDateString()])
            ->groupBy('student_id')
            ->toBase()
            ->get()
            ->map(function (\stdClass $row): array {
                $dueSoFar = (float) $row->due_so_far;
                $totalPaid = (float) $row->total_paid;

                return [
                    'student_id' => (int) $row->student_id,
                    'due_so_far' => $dueSoFar,
                    'total_paid' => $totalPaid,
                    'balance' => $dueSoFar - $totalPaid,
                ];
            });
    }

    /**
     * @return array{student_id: int, due_so_far: float, total_paid: float, balance: float}|null
     */
    public function balanceForStudent(int $studentId, int $schoolYearId, ?int $ownerId = null): ?array
    {
        return $this->balances($schoolYearId, $ownerId, $studentId)->first();
    }
}
