<?php

declare(strict_types=1);

namespace App\Livewire\GuardianPortal\Concerns;

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Le portail parents n'utilise pas les Policies "staff" (StudentPolicy...),
 * qui autorisent tout membre de l'établissement à consulter n'importe quel
 * élève. Un tuteur ne doit voir que SES enfants — vérifié explicitement ici
 * via la liaison guardian_student plutôt que par rôle seul.
 */
trait EnsuresGuardianAccess
{
    protected function currentGuardian(): Guardian
    {
        /** @var User $user */
        $user = Auth::user();

        $guardian = $user->guardianProfile;

        abort_if($guardian === null, 403, __('Aucun profil tuteur associé à ce compte.'));

        return $guardian;
    }

    protected function authorizeGuardianAccess(Student $student): Guardian
    {
        $guardian = $this->currentGuardian();

        abort_unless(
            $guardian->students()
                ->where('students.id', $student->id)
                ->wherePivot('status', GuardianLinkStatus::Approved)
                ->exists(),
            403,
            __("Cet élève n'est pas rattaché à votre compte.")
        );

        return $guardian;
    }
}
