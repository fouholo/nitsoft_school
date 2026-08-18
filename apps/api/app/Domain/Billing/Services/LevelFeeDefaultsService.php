<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\LevelFee;

/**
 * Calcule les montants par défaut (frais d'inscription + tranches) d'un
 * niveau pour préremplir les champs financiers d'une inscription — voir
 * docs/superpowers/specs/2026-08-18-refonte-factures-scolarite-design.md.
 */
class LevelFeeDefaultsService
{
    /**
     * @return array{registration_amount: float, installment_1_amount: float, installment_2_amount: float, installment_3_amount: float, installment_4_amount: float, installment_5_amount: float, installment_6_amount: float, installment_7_amount: float}
     */
    public function defaultsFor(int $schoolYearId, int $levelId, bool $isAssigned): array
    {
        $levelFee = LevelFee::where('school_year_id', $schoolYearId)
            ->where('level_id', $levelId)
            ->with('installmentAmounts.installment')
            ->first();

        $registrationAmount = $levelFee
            ? (float) ($isAssigned && $levelFee->registration_amount_assigned !== null
                ? $levelFee->registration_amount_assigned
                : $levelFee->registration_amount)
            : 0.0;

        return [
            'registration_amount' => $registrationAmount,
            ...$this->installmentDefaults($levelFee, $isAssigned),
        ];
    }

    /**
     * @return array{installment_1_amount: float, installment_2_amount: float, installment_3_amount: float, installment_4_amount: float, installment_5_amount: float, installment_6_amount: float, installment_7_amount: float}
     */
    public function installmentDefaultsFor(int $schoolYearId, int $levelId, bool $isAssigned): array
    {
        $levelFee = LevelFee::where('school_year_id', $schoolYearId)
            ->where('level_id', $levelId)
            ->with('installmentAmounts.installment')
            ->first();

        return $this->installmentDefaults($levelFee, $isAssigned);
    }

    /**
     * @return array{installment_1_amount: float, installment_2_amount: float, installment_3_amount: float, installment_4_amount: float, installment_5_amount: float, installment_6_amount: float, installment_7_amount: float}
     */
    private function installmentDefaults(?LevelFee $levelFee, bool $isAssigned): array
    {
        $amounts = [];

        foreach (range(1, 7) as $position) {
            $levelFeeInstallment = (! $isAssigned && $levelFee)
                ? $levelFee->installmentAmounts->firstWhere('installment.position', $position)
                : null;

            $amounts["installment_{$position}_amount"] = $levelFeeInstallment !== null ? (float) $levelFeeInstallment->amount : 0.0;
        }

        return $amounts;
    }
}
