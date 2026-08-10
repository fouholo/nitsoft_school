<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Establishments\Support\RolePermissions;
use App\Domain\Grading\Models\GradeSheet;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class GradeSheetPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user) && $user->currentRole() !== 'caissier';
    }

    public function view(User $user, GradeSheet $gradeSheet): bool
    {
        return $this->belongsToSameEstablishment($user, $gradeSheet->establishment_id)
            && $user->currentRole() !== 'caissier';
    }

    /**
     * Éducateur (seul rôle "admin-tier" habilité à saisir des notes, cf.
     * RolePermissions::MATRIX['grades.enter']), ou enseignant avec au moins
     * une affectation. Le choix précis de la classe/matière est ensuite
     * restreint dans le composant Livewire aux seules affectations réelles de
     * l'enseignant, revérifiées avant écriture (defense in depth).
     */
    public function create(User $user): bool
    {
        if (RolePermissions::can($user->currentRole(), 'grades.enter')) {
            return true;
        }

        return $user->currentRole() === 'enseignant'
            && TeacherAssignment::query()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, GradeSheet $gradeSheet): bool
    {
        if (! $this->belongsToSameEstablishment($user, $gradeSheet->establishment_id)) {
            return false;
        }

        if (RolePermissions::can($user->currentRole(), 'grades.enter')) {
            return true;
        }

        return $user->id === $gradeSheet->teacher_id
            && $user->isAssignedToClassroom($gradeSheet->classroom_id, $gradeSheet->subject_id);
    }

    public function delete(User $user, GradeSheet $gradeSheet): bool
    {
        return $this->update($user, $gradeSheet);
    }
}
