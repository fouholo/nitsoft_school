<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\TeacherAssignment;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class TeacherAssignmentPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function view(User $user, TeacherAssignment $teacherAssignment): bool
    {
        return $this->belongsToSameEstablishment($user, $teacherAssignment->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function delete(User $user, TeacherAssignment $teacherAssignment): bool
    {
        return $this->belongsToSameEstablishment($user, $teacherAssignment->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }
}
