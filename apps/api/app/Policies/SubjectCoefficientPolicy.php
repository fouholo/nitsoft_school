<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\SubjectCoefficient;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class SubjectCoefficientPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function view(User $user, SubjectCoefficient $subjectCoefficient): bool
    {
        return $this->belongsToSameEstablishment($user, $subjectCoefficient->establishment_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function update(User $user, SubjectCoefficient $subjectCoefficient): bool
    {
        return $this->belongsToSameEstablishment($user, $subjectCoefficient->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }

    public function delete(User $user, SubjectCoefficient $subjectCoefficient): bool
    {
        return $this->update($user, $subjectCoefficient);
    }
}
