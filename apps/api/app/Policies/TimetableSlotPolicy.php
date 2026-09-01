<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Support\RolePermissions;
use App\Domain\Timetable\Models\TimetableSlot;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class TimetableSlotPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function create(User $user): bool
    {
        return $this->isLocalAdminOfCurrentEstablishment($user)
            && RolePermissions::can($user->currentRole(), 'timetable.manage');
    }

    public function update(User $user, TimetableSlot $timetableSlot): bool
    {
        return $this->belongsToSameEstablishment($user, $timetableSlot->establishment_id)
            && $this->isLocalAdminOfCurrentEstablishment($user)
            && RolePermissions::can($user->currentRole(), 'timetable.manage');
    }

    public function delete(User $user, TimetableSlot $timetableSlot): bool
    {
        return $this->update($user, $timetableSlot);
    }
}
