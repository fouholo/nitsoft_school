<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\SubjectCoefficient;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class SubjectCoefficientPolicy
{
    use ChecksEstablishmentMembership;

    /**
     * Réservé au secondaire — au préscolaire/primaire, les coefficients par
     * matière sont portés par PrimarySubject (catalogue global SaaS), cf.
     * docs/superpowers/specs/2026-08-15-matieres-primaire-catalogue-coefficients-design.md.
     * Réservé en plus à fondateur/directeur/gestionnaire/éducateur — un
     * caissier ou un enseignant ne doit même pas voir l'écran, pas
     * seulement être empêché d'y écrire.
     */
    public function viewAny(User $user): bool
    {
        if (! $this->canManage($user)) {
            return false;
        }

        $establishment = Establishment::find((int) app('currentEstablishmentId'));

        return $establishment?->isSecondaire() ?? false;
    }

    public function view(User $user, SubjectCoefficient $subjectCoefficient): bool
    {
        return $this->belongsToSameEstablishment($user, $subjectCoefficient->establishment_id);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, SubjectCoefficient $subjectCoefficient): bool
    {
        return $this->belongsToSameEstablishment($user, $subjectCoefficient->establishment_id)
            && $this->canManage($user);
    }

    public function delete(User $user, SubjectCoefficient $subjectCoefficient): bool
    {
        return $this->update($user, $subjectCoefficient);
    }

    /**
     * currentRole() plutôt que isAdminOfCurrentEstablishment() pour le
     * fondateur : ce dernier ne reconnaît un fondateur que via une
     * Foundation, pas un fondateur attaché directement à un établissement
     * indépendant (establishment_user.role = 'fondateur') — voir mémoire
     * projet "has_admin_rights_foundation_only_fondateur_gap".
     */
    private function canManage(User $user): bool
    {
        return in_array($user->currentRole(), ['fondateur', 'directeur', 'gestionnaire'], true)
            || RolePermissions::can($user->currentRole(), 'subject_coefficients.write');
    }
}
