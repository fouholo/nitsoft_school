<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Discount;
use App\Domain\Enrollment\Models\Enrollment;

/**
 * Applique une réduction (Discount) aux tranches de scolarité d'une
 * inscription — voir
 * docs/superpowers/specs/2026-08-18-refonte-factures-scolarite-design.md,
 * section 3.
 */
class DiscountService
{
    public function __construct(private readonly LevelFeeDefaultsService $levelFeeDefaults)
    {
    }

    /**
     * Recharge les 7 tranches de l'inscription aux valeurs par défaut du
     * niveau (jamais le frais d'inscription), puis retranche la réduction
     * en partant de la tranche 7 vers la tranche 1, sans jamais descendre
     * sous 0. Repartir des valeurs par défaut à chaque application évite un
     * double retranchement si la réduction est modifiée plusieurs fois.
     */
    public function applyToEnrollment(Enrollment $enrollment, Discount $discount): void
    {
        $this->resetInstallmentsToLevelDefaults($enrollment);

        $totalInstallments = array_sum($enrollment->installmentAmountsByPosition());

        $amountToDeduct = $discount->type === 'percentage'
            ? round($totalInstallments * ((float) $discount->value / 100), 2)
            : (float) $discount->value;

        foreach (range(7, 1) as $position) {
            if ($amountToDeduct <= 0) {
                break;
            }

            $field = "installment_{$position}_amount";
            $current = (float) ($enrollment->{$field} ?? 0);
            $deduction = min($current, $amountToDeduct);

            $enrollment->{$field} = round($current - $deduction, 2);
            $amountToDeduct -= $deduction;
        }

        $enrollment->save();
    }

    public function resetToLevelDefaults(Enrollment $enrollment): void
    {
        $this->resetInstallmentsToLevelDefaults($enrollment);
        $enrollment->save();
    }

    private function resetInstallmentsToLevelDefaults(Enrollment $enrollment): void
    {
        $defaults = $this->levelFeeDefaults->installmentDefaultsFor(
            $enrollment->school_year_id,
            $enrollment->classroom->level_id,
            (bool) $enrollment->is_assigned,
        );

        $enrollment->fill($defaults);
    }
}
