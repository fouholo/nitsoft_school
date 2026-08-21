<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing\Concerns;

use App\Domain\Billing\Models\Installment;
use App\Domain\Enrollment\Models\Enrollment;

/**
 * Construit les lignes "reste à payer" (frais d'inscription + tranches non
 * soldées) d'une inscription pour la lettre de relance — voir
 * docs/superpowers/specs/2026-08-21-lettre-relance-paiement-design.md.
 */
trait BuildsPaymentReminderRows
{
    /**
     * @return array{rows: list<array{label: string, due_date: ?\Carbon\Carbon, due: float, paid: float, remaining: float}>, total: float}
     */
    private function reminderRowsFor(Enrollment $enrollment): array
    {
        $rows = [];

        $registrationDue = (float) ($enrollment->registration_amount ?? 0);
        $registrationPaid = $enrollment->registrationAmountPaid();

        if ($registrationDue > 0 && $registrationPaid < $registrationDue) {
            $rows[] = [
                'label' => "Frais d'inscription",
                'due_date' => null,
                'due' => $registrationDue,
                'paid' => $registrationPaid,
                'remaining' => $registrationDue - $registrationPaid,
            ];
        }

        $installmentLabels = Installment::where('school_year_id', $enrollment->school_year_id)
            ->pluck('label', 'position');

        foreach ($enrollment->tuitionInstallmentsWithStatus() as $installment) {
            if ($installment['status'] === 'paid') {
                continue;
            }

            $rows[] = [
                'label' => $installmentLabels->get($installment['position'], "Tranche {$installment['position']}"),
                'due_date' => $installment['due_date'],
                'due' => $installment['amount'],
                'paid' => $installment['paid'],
                'remaining' => $installment['amount'] - $installment['paid'],
            ];
        }

        return [
            'rows' => $rows,
            'total' => array_sum(array_column($rows, 'remaining')),
        ];
    }
}
