<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Réservé aux établissements is_arabe. Miroir de GradeSheetPolicy : l'accès
 * large à la saisie de notes suit RolePermissions::MATRIX['grades.enter']
 * (educateur uniquement — ni directeur/gestionnaire/fondateur, qui n'ont pas
 * plus d'accès large côté français, cf. GradeSheetPolicy), en plus de
 * l'enseignant affecté à son groupe précis (niveau/série/matière) — voir
 * docs/superpowers/specs/2026-08-21-arabe-affectations-notes-design.md.
 */
class ArabicGradeSheetPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        if (! $this->isMemberOfCurrentEstablishment($user) || $user->currentRole() === 'caissier') {
            return false;
        }

        $establishment = Establishment::find((int) app('currentEstablishmentId'));

        return $establishment !== null && $establishment->is_arabe;
    }

    public function view(User $user, ArabicGradeSheet $arabicGradeSheet): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicGradeSheet->establishment_id)
            && $user->currentRole() !== 'caissier';
    }

    public function create(User $user): bool
    {
        if (RolePermissions::can($user->currentRole(), 'grades.enter')) {
            return true;
        }

        return $user->currentRole() === 'enseignant'
            && ArabicTeacherAssignment::query()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, ArabicGradeSheet $arabicGradeSheet): bool
    {
        if (! $this->belongsToSameEstablishment($user, $arabicGradeSheet->establishment_id)) {
            return false;
        }

        if (RolePermissions::can($user->currentRole(), 'grades.enter')) {
            return true;
        }

        if ($user->id !== $arabicGradeSheet->teacher_id) {
            return false;
        }

        return $user->isAssignedToArabicGroup(
            $arabicGradeSheet->arabic_level_id,
            $arabicGradeSheet->arabic_serie_id,
            $arabicGradeSheet->arabic_subject_id,
        );
    }

    public function delete(User $user, ArabicGradeSheet $arabicGradeSheet): bool
    {
        return $this->update($user, $arabicGradeSheet);
    }
}
