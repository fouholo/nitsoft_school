<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Réservé aux établissements is_arabe. Action de configuration : pas
 * d'élargissement educateur, contrairement à la saisie de notes
 * (ArabicGradeSheetPolicy) — même logique que ArabicSubjectCoefficientPolicy.
 */
class ArabicTeacherAssignmentPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        if (! $this->isMemberOfCurrentEstablishment($user)) {
            return false;
        }

        $establishment = Establishment::find((int) app('currentEstablishmentId'));

        return $establishment !== null && $establishment->is_arabe;
    }

    public function view(User $user, ArabicTeacherAssignment $arabicTeacherAssignment): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicTeacherAssignment->establishment_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->currentRole(), ['fondateur', 'directeur', 'gestionnaire'], true);
    }

    public function delete(User $user, ArabicTeacherAssignment $arabicTeacherAssignment): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicTeacherAssignment->establishment_id)
            && in_array($user->currentRole(), ['fondateur', 'directeur', 'gestionnaire'], true);
    }
}
