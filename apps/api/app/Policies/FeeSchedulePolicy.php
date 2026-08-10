<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\FeeSchedule;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class FeeSchedulePolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'finance.access');
    }

    public function view(User $user, FeeSchedule $feeSchedule): bool
    {
        return $this->belongsToSameEstablishment($user, $feeSchedule->establishment_id)
            && RolePermissions::can($user->currentRole(), 'finance.access');
    }

    public function create(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'fee_schedules.write');
    }

    public function update(User $user, FeeSchedule $feeSchedule): bool
    {
        return $this->belongsToSameEstablishment($user, $feeSchedule->establishment_id)
            && RolePermissions::can($user->currentRole(), 'fee_schedules.write');
    }

    public function delete(User $user, FeeSchedule $feeSchedule): bool
    {
        return $this->update($user, $feeSchedule);
    }
}
