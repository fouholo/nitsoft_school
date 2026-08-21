<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Arabic\Models\ArabicSubjectCoefficient;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Réservé aux établissements is_arabe — équivalent, pour la filière arabe,
 * de SubjectCoefficientPolicy (réservé au secondaire côté français). Créer/
 * modifier est réservé à fondateur/directeur/gestionnaire, sans
 * élargissement à educateur contrairement au français
 * (subject_coefficients.write) — voir
 * docs/superpowers/specs/2026-08-21-arabe-fondations-design.md.
 *
 * Vérifie le rôle via currentRole() plutôt que
 * isAdminOfCurrentEstablishment() : cette dernière (hasAdminRightsOn) ne
 * reconnaît un "fondateur" que via une Foundation
 * (User::isFounderOfEstablishment) et ignore le cas — pourtant valide et
 * déjà utilisé ailleurs dans l'app (ex. Bilan financier) — d'un fondateur
 * rattaché en direct à un établissement indépendant (establishment_user.role
 * = 'fondateur' sans Foundation).
 */
class ArabicSubjectCoefficientPolicy
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

    public function view(User $user, ArabicSubjectCoefficient $arabicSubjectCoefficient): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicSubjectCoefficient->establishment_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->currentRole(), ['fondateur', 'directeur', 'gestionnaire'], true);
    }

    public function update(User $user, ArabicSubjectCoefficient $arabicSubjectCoefficient): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicSubjectCoefficient->establishment_id)
            && in_array($user->currentRole(), ['fondateur', 'directeur', 'gestionnaire'], true);
    }

    public function delete(User $user, ArabicSubjectCoefficient $arabicSubjectCoefficient): bool
    {
        return $this->update($user, $arabicSubjectCoefficient);
    }
}
