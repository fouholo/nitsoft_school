<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Support\RolePermissions;
use App\Domain\Timetable\Models\ArabicTimetableSession;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Mêmes règles que TimetableSessionPolicy, réservées aux écoles
 * franco-arabes.
 */
class ArabicTimetableSessionPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user) && $this->currentEstablishmentIsArabe();
    }

    /**
     * Liste des classes arabes et grille d'une classe : personnel
     * administratif, comme TimetableSessionPolicy::browseStaff().
     */
    public function browseStaff(User $user): bool
    {
        return $this->viewAny($user) && $user->currentRole() !== 'enseignant';
    }

    public function create(User $user): bool
    {
        return $this->isLocalAdminOfCurrentEstablishment($user)
            && RolePermissions::can($user->currentRole(), 'timetable.manage')
            && $this->currentEstablishmentIsArabe();
    }

    public function update(User $user, ArabicTimetableSession $arabicTimetableSession): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicTimetableSession->establishment_id)
            && $this->create($user);
    }

    public function delete(User $user, ArabicTimetableSession $arabicTimetableSession): bool
    {
        return $this->update($user, $arabicTimetableSession);
    }
}
