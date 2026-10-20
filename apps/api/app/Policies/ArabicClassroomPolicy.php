<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Mêmes droits que ClassroomPolicy, réservés aux écoles franco-arabes —
 * voir docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */
class ArabicClassroomPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user) && $this->currentEstablishmentIsArabe();
    }

    public function view(User $user, ArabicClassroom $arabicClassroom): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicClassroom->establishment_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user) && $this->currentEstablishmentIsArabe();
    }

    public function update(User $user, ArabicClassroom $arabicClassroom): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicClassroom->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user)
            && $this->currentEstablishmentIsArabe();
    }

    public function delete(User $user, ArabicClassroom $arabicClassroom): bool
    {
        return $this->update($user, $arabicClassroom);
    }
}
