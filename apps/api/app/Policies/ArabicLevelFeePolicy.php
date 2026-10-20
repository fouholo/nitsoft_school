<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\ArabicLevelFee;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Mêmes droits que LevelFeePolicy, réservés aux écoles franco-arabes —
 * voir docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */
class ArabicLevelFeePolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'finance.access') && $this->currentEstablishmentIsArabe();
    }

    public function view(User $user, ArabicLevelFee $arabicLevelFee): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicLevelFee->establishment_id)
            && RolePermissions::can($user->currentRole(), 'finance.access');
    }

    public function create(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'tuition_fees.write') && $this->currentEstablishmentIsArabe();
    }

    public function update(User $user, ArabicLevelFee $arabicLevelFee): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicLevelFee->establishment_id)
            && RolePermissions::can($user->currentRole(), 'tuition_fees.write')
            && $this->currentEstablishmentIsArabe();
    }

    public function delete(User $user, ArabicLevelFee $arabicLevelFee): bool
    {
        return $this->update($user, $arabicLevelFee);
    }
}
