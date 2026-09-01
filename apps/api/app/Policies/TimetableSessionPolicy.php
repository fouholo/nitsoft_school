<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Support\RolePermissions;
use App\Domain\Timetable\Models\TimetableSession;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class TimetableSessionPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    /**
     * Le personnel administratif (tous rôles hors enseignant) consulte
     * n'importe quelle séance ; un enseignant ne consulte que les siennes —
     * voir spec, périmètre enseignant.
     */
    public function view(User $user, TimetableSession $timetableSession): bool
    {
        if (! $this->belongsToSameEstablishment($user, $timetableSession->establishment_id)) {
            return false;
        }

        return $timetableSession->user_id === $user->id
            || $user->currentRole() !== 'enseignant';
    }

    public function create(User $user): bool
    {
        return $this->isLocalAdminOfCurrentEstablishment($user)
            && RolePermissions::can($user->currentRole(), 'timetable.manage');
    }

    public function update(User $user, TimetableSession $timetableSession): bool
    {
        return $this->belongsToSameEstablishment($user, $timetableSession->establishment_id)
            && $this->isLocalAdminOfCurrentEstablishment($user)
            && RolePermissions::can($user->currentRole(), 'timetable.manage');
    }

    public function delete(User $user, TimetableSession $timetableSession): bool
    {
        return $this->update($user, $timetableSession);
    }
}
