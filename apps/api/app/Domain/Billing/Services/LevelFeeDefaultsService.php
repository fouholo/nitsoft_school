<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Billing\Models\ArabicLevelFee;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Enrollment\Models\Enrollment;

/**
 * Calcule les montants par défaut (frais d'inscription + tranches) d'un
 * niveau pour préremplir les champs financiers d'une inscription — voir
 * docs/superpowers/specs/2026-08-18-refonte-factures-scolarite-design.md.
 * Le niveau est celui de la classe principale de l'élève : niveau français
 * (LevelFee) ou niveau arabe (ArabicLevelFee, sans montant « affecté ») —
 * voir docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */
class LevelFeeDefaultsService
{
    /**
     * @return array{registration_amount: float, installment_1_amount: float, installment_2_amount: float, installment_3_amount: float, installment_4_amount: float, installment_5_amount: float, installment_6_amount: float, installment_7_amount: float}
     */
    public function defaultsFor(int $schoolYearId, int $levelId, bool $isAssigned): array
    {
        $levelFee = $this->levelFee($schoolYearId, $levelId);

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
     * @return array{registration_amount: float, installment_1_amount: float, installment_2_amount: float, installment_3_amount: float, installment_4_amount: float, installment_5_amount: float, installment_6_amount: float, installment_7_amount: float}
     */
    public function arabicDefaultsFor(int $schoolYearId, int $arabicLevelId): array
    {
        $arabicLevelFee = $this->arabicLevelFee($schoolYearId, $arabicLevelId);

        return [
            'registration_amount' => $arabicLevelFee ? (float) $arabicLevelFee->registration_amount : 0.0,
            ...$this->installmentDefaults($arabicLevelFee, false),
        ];
    }

    /**
     * @return array{installment_1_amount: float, installment_2_amount: float, installment_3_amount: float, installment_4_amount: float, installment_5_amount: float, installment_6_amount: float, installment_7_amount: float}
     */
    public function installmentDefaultsFor(int $schoolYearId, int $levelId, bool $isAssigned): array
    {
        return $this->installmentDefaults($this->levelFee($schoolYearId, $levelId), $isAssigned);
    }

    /**
     * Tranches par défaut de l'inscription selon sa classe principale ; une
     * inscription sans classe principale n'a pas de grille (tranches à 0).
     *
     * @return array{installment_1_amount: float, installment_2_amount: float, installment_3_amount: float, installment_4_amount: float, installment_5_amount: float, installment_6_amount: float, installment_7_amount: float}
     */
    public function installmentDefaultsForEnrollment(Enrollment $enrollment): array
    {
        $mainClassroom = $enrollment->mainClassroom();

        $fee = match (true) {
            $mainClassroom instanceof ArabicClassroom => $this->arabicLevelFee($enrollment->school_year_id, $mainClassroom->arabic_level_id),
            $mainClassroom !== null => $this->levelFee($enrollment->school_year_id, $mainClassroom->level_id),
            default => null,
        };

        return $this->installmentDefaults($fee, ! $mainClassroom instanceof ArabicClassroom && (bool) $enrollment->is_assigned);
    }

    private function levelFee(int $schoolYearId, int $levelId): ?LevelFee
    {
        return LevelFee::where('school_year_id', $schoolYearId)
            ->where('level_id', $levelId)
            ->with('installmentAmounts.installment')
            ->first();
    }

    private function arabicLevelFee(int $schoolYearId, int $arabicLevelId): ?ArabicLevelFee
    {
        return ArabicLevelFee::where('school_year_id', $schoolYearId)
            ->where('arabic_level_id', $arabicLevelId)
            ->with('installmentAmounts.installment')
            ->first();
    }

    /**
     * @return array{installment_1_amount: float, installment_2_amount: float, installment_3_amount: float, installment_4_amount: float, installment_5_amount: float, installment_6_amount: float, installment_7_amount: float}
     */
    private function installmentDefaults(LevelFee|ArabicLevelFee|null $levelFee, bool $isAssigned): array
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
