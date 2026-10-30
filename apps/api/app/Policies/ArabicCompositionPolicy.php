<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicComposition;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Compositions du primaire arabe, réservées aux écoles franco-arabes de type
 * préscolaire-primaire — voir
 * docs/superpowers/specs/2026-10-30-compositions-primaire-arabe-design.md.
 */
class ArabicCompositionPolicy
{
    use ChecksEstablishmentMembership;

    private const ADMIN_ROLES = ['fondateur', 'directeur', 'gestionnaire'];

    /**
     * Personnel pédagogique et administratif : ni caissier, ni parent.
     */
    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user)
            && $this->currentEstablishmentIsArabicPrimary()
            && ! in_array($user->currentRole(), ['caissier', 'parent'], true);
    }

    /**
     * Comme en français, une composition vaut pour toute l'école : seul le
     * directeur la crée.
     */
    public function create(User $user): bool
    {
        return $user->currentRole() === 'directeur'
            && $this->isMemberOfCurrentEstablishment($user)
            && $this->currentEstablishmentIsArabicPrimary();
    }

    public function delete(User $user, ArabicComposition $arabicComposition): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicComposition->establishment_id)
            && $this->create($user);
    }

    /**
     * Saisir les notes d'une classe et consulter ses relevés.
     */
    public function accessClassroom(User $user, ArabicClassroom $arabicClassroom): bool
    {
        if (! $this->belongsToSameEstablishment($user, $arabicClassroom->establishment_id) || ! $this->viewAny($user)) {
            return false;
        }

        return in_array($user->currentRole(), self::ADMIN_ROLES, true)
            || RolePermissions::can($user->currentRole(), 'grades.enter')
            || $user->isAssignedToArabicClassroom($arabicClassroom->id);
    }

    public function generate(User $user): bool
    {
        return $this->viewAny($user)
            && (in_array($user->currentRole(), self::ADMIN_ROLES, true)
                || RolePermissions::can($user->currentRole(), 'report_cards.generate'));
    }
}
