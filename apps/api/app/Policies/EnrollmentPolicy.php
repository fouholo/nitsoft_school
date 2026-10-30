<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enrollment\Models\Enrollment;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class EnrollmentPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $this->belongsToSameEstablishment($user, $enrollment->establishment_id);
    }

    public function create(User $user): bool
    {
        return $this->canManageEnrollments($user);
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $this->belongsToSameEstablishment($user, $enrollment->establishment_id)
            && $this->canManageEnrollments($user);
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $this->update($user, $enrollment);
    }

    /**
     * isAdminOfCurrentEstablishment() ne reconnaît un fondateur que via une
     * fondation : currentRole() couvre aussi celui d'une école indépendante,
     * ainsi que le caissier, qui inscrit les élèves au guichet.
     */
    private function canManageEnrollments(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user)
            || in_array($user->currentRole(), ['fondateur', 'caissier'], true);
    }
}
