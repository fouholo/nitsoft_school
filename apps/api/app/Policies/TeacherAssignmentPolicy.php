<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * L'affectation d'un enseignant à une classe relève du domaine "enseignants"
 * (roster) : gatée par le pouvoir LOCAL_ADMIN/GENERAL_ADMIN (isLocalAdminOf),
 * puis par le rôle du titulaire (RolePermissions::MATRIX['staff.assign']) —
 * voir docs/superpowers/specs/2026-08-09-privileges-par-role-design.md,
 * section 5. La consultation (viewAny/view) reste ouverte à tout admin de
 * l'établissement, non gatée par le pouvoir.
 */
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
        return $this->isLocalAdminOfCurrentEstablishment($user)
            && RolePermissions::can($user->currentRole(), 'staff.assign');
    }

    public function delete(User $user, TeacherAssignment $teacherAssignment): bool
    {
        return $this->belongsToSameEstablishment($user, $teacherAssignment->establishment_id)
            && $this->isLocalAdminOfCurrentEstablishment($user)
            && RolePermissions::can($user->currentRole(), 'staff.assign');
    }
}
