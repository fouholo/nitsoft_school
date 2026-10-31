<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Discount;
use App\Domain\Enrollment\Models\Enrollment;

/**
 * Applique une réduction (Discount) à une inscription, sur ses tranches de
 * scolarité et sur ses frais d'inscription, indépendamment — voir
 * docs/superpowers/specs/2026-08-18-refonte-factures-scolarite-design.md,
 * section 3, et 2026-10-31-reductions-droits-inscription-design.md.
 */
class DiscountService
{
    public function __construct(private readonly LevelFeeDefaultsService $levelFeeDefaults)
    {
    }

    /**
     * Chaque partie repart de la grille du niveau avant d'être réduite, ce
     * qui évite un double retranchement si la réduction est modifiée
     * plusieurs fois. Une partie sans réduction, ni avant ni après, n'est pas
     * touchée : un montant ajusté à la main y est conservé.
     */
    public function applyToEnrollment(
        Enrollment $enrollment,
        Discount $discount,
        bool $hadTuitionDiscount = false,
        bool $hadRegistrationDiscount = false,
    ): void {
        if ($discount->hasTuitionDiscount()) {
            $this->resetInstallmentsToLevelDefaults($enrollment);
            $this->deductFromInstallments($enrollment, $discount);
        } elseif ($hadTuitionDiscount) {
            $this->resetInstallmentsToLevelDefaults($enrollment);
        }

        if ($discount->hasRegistrationDiscount()) {
            $registrationAmount = $this->levelFeeDefaults->registrationDefaultForEnrollment($enrollment);
            $deduction = $this->deductionFrom($registrationAmount, (string) $discount->registration_type, (float) $discount->registration_value);

            $enrollment->registration_amount = round(max(0.0, $registrationAmount - $deduction), 2);
        } elseif ($hadRegistrationDiscount) {
            $enrollment->registration_amount = $this->levelFeeDefaults->registrationDefaultForEnrollment($enrollment);
        }

        $enrollment->save();
    }

    /**
     * Remet à la grille du niveau les seules parties que la réduction
     * supprimée concernait.
     */
    public function removeFromEnrollment(Enrollment $enrollment, Discount $discount): void
    {
        if ($discount->hasTuitionDiscount()) {
            $this->resetInstallmentsToLevelDefaults($enrollment);
        }

        if ($discount->hasRegistrationDiscount()) {
            $enrollment->registration_amount = $this->levelFeeDefaults->registrationDefaultForEnrollment($enrollment);
        }

        $enrollment->save();
    }

    /**
     * Retranche la réduction en partant de la tranche 7 vers la tranche 1,
     * sans jamais descendre sous 0.
     */
    private function deductFromInstallments(Enrollment $enrollment, Discount $discount): void
    {
        $totalInstallments = array_sum($enrollment->installmentAmountsByPosition());
        $amountToDeduct = $this->deductionFrom($totalInstallments, (string) $discount->type, (float) $discount->value);

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
    }

    private function deductionFrom(float $amount, string $type, float $value): float
    {
        return $type === 'percentage' ? round($amount * ($value / 100), 2) : $value;
    }

    private function resetInstallmentsToLevelDefaults(Enrollment $enrollment): void
    {
        $enrollment->fill($this->levelFeeDefaults->installmentDefaultsForEnrollment($enrollment));
    }
}
