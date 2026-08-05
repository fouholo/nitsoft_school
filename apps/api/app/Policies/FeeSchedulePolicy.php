<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\FeeSchedule;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class FeeSchedulePolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isBillingManagerOfCurrentEstablishment($user);
    }

    public function view(User $user, FeeSchedule $feeSchedule): bool
    {
        return $this->belongsToSameEstablishment($user, $feeSchedule->establishment_id)
            && $this->isBillingManagerOfCurrentEstablishment($user);
    }

    public function create(User $user): bool
    {
        return $this->isBillingManagerOfCurrentEstablishment($user);
    }

    public function update(User $user, FeeSchedule $feeSchedule): bool
    {
        return $this->belongsToSameEstablishment($user, $feeSchedule->establishment_id)
            && $this->isBillingManagerOfCurrentEstablishment($user);
    }

    public function delete(User $user, FeeSchedule $feeSchedule): bool
    {
        return $this->update($user, $feeSchedule);
    }
}
