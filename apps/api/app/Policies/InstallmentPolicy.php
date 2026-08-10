<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\Installment;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class InstallmentPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'finance.access');
    }

    public function view(User $user, Installment $installment): bool
    {
        return $this->belongsToSameEstablishment($user, $installment->establishment_id)
            && RolePermissions::can($user->currentRole(), 'finance.access');
    }

    public function create(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'tuition_fees.write');
    }

    public function update(User $user, Installment $installment): bool
    {
        return $this->belongsToSameEstablishment($user, $installment->establishment_id)
            && RolePermissions::can($user->currentRole(), 'tuition_fees.write');
    }

    public function delete(User $user, Installment $installment): bool
    {
        return $this->update($user, $installment);
    }
}
