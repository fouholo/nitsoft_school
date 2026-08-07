<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enrollment\Models\GuardianStudentPivot;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class GuardianStudentPivotPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function update(User $user, GuardianStudentPivot $pivot): bool
    {
        return $this->isAdminOfCurrentEstablishment($user)
            && app()->bound('currentEstablishmentId')
            && (int) app('currentEstablishmentId') === $pivot->establishment_id;
    }
}
