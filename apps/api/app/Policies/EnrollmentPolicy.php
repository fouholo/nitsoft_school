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
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $this->belongsToSameEstablishment($user, $enrollment->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $this->update($user, $enrollment);
    }
}
