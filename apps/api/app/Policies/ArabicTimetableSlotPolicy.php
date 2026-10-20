<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Support\RolePermissions;
use App\Domain\Timetable\Models\ArabicTimetableSlot;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Mêmes règles que TimetableSlotPolicy, réservées aux écoles franco-arabes.
 */
class ArabicTimetableSlotPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user) && $this->currentEstablishmentIsArabe();
    }

    public function create(User $user): bool
    {
        return $this->isLocalAdminOfCurrentEstablishment($user)
            && RolePermissions::can($user->currentRole(), 'timetable.manage')
            && $this->currentEstablishmentIsArabe();
    }

    public function update(User $user, ArabicTimetableSlot $arabicTimetableSlot): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicTimetableSlot->establishment_id)
            && $this->create($user);
    }

    public function delete(User $user, ArabicTimetableSlot $arabicTimetableSlot): bool
    {
        return $this->update($user, $arabicTimetableSlot);
    }
}
