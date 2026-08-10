<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class StudentPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function view(User $user, Student $student): bool
    {
        return $this->belongsToSameEstablishment($user, $student->establishment_id);
    }

    public function create(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'students.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $this->belongsToSameEstablishment($user, $student->establishment_id)
            && RolePermissions::can($user->currentRole(), 'students.update');
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->belongsToSameEstablishment($user, $student->establishment_id)
            && RolePermissions::can($user->currentRole(), 'students.delete');
    }
}
