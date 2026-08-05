<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\Subject;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class SubjectPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function view(User $user, Subject $subject): bool
    {
        return $this->belongsToSameEstablishment($user, $subject->establishment_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function update(User $user, Subject $subject): bool
    {
        return $this->belongsToSameEstablishment($user, $subject->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $this->update($user, $subject);
    }
}
