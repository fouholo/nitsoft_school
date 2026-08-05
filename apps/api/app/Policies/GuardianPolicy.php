<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enrollment\Models\Guardian;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class GuardianPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $this->belongsToSameEstablishment($user, $guardian->establishment_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $this->belongsToSameEstablishment($user, $guardian->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $this->update($user, $guardian);
    }
}
