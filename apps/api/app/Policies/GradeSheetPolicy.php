<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Grading\Models\GradeSheet;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class GradeSheetPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function view(User $user, GradeSheet $gradeSheet): bool
    {
        return $this->belongsToSameEstablishment($user, $gradeSheet->establishment_id);
    }

    /**
     * Vérification grossière : l'utilisateur est admin, ou enseignant avec au
     * moins une affectation. Le choix précis de la classe/matière est ensuite
     * restreint dans le composant Livewire aux seules affectations réelles de
     * l'enseignant, revérifiées avant écriture (defense in depth).
     */
    public function create(User $user): bool
    {
        if ($this->isAdminOfCurrentEstablishment($user)) {
            return true;
        }

        return $user->currentRole() === 'teacher'
            && TeacherAssignment::query()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, GradeSheet $gradeSheet): bool
    {
        if (! $this->belongsToSameEstablishment($user, $gradeSheet->establishment_id)) {
            return false;
        }

        if ($this->isAdminOfCurrentEstablishment($user)) {
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
