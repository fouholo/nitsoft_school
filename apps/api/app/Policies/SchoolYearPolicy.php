<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\SchoolYear;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class SchoolYearPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function view(User $user, SchoolYear $schoolYear): bool
    {
        return $this->belongsToSameEstablishment($user, $schoolYear->establishment_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function update(User $user, SchoolYear $schoolYear): bool
    {
        return $this->belongsToSameEstablishment($user, $schoolYear->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }

    public function delete(User $user, SchoolYear $schoolYear): bool
    {
        return $this->update($user, $schoolYear);
    }
}
