<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\Classroom;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class ClassroomPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function view(User $user, Classroom $classroom): bool
    {
        return $this->belongsToSameEstablishment($user, $classroom->establishment_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $this->belongsToSameEstablishment($user, $classroom->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }

    public function delete(User $user, Classroom $classroom): bool
    {
        return $this->update($user, $classroom);
    }
}
