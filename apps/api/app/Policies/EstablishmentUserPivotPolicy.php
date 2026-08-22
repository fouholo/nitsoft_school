<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;

/**
 * Actions sur une ligne staff existante (activer/désactiver/supprimer) —
 * la création passe par EstablishmentPolicy::manageStaff/manageOrganization
 * (pas de cible existante à ce stade). La matrice RolePermissions porte ici
 * sur le rôle de l'ACTEUR (le titulaire du pouvoir qui agit), pas sur le
 * rôle de la cible — voir docs/superpowers/specs/2026-08-09-privileges-par-role-design.md,
 * section 5. On résout ce rôle via `roleFor($target->establishment_id)`,
 * pas `currentRole()` : ces écrans agrègent potentiellement plusieurs
 * établissements (GENERAL_ADMIN d'une fondation), et l'établissement
 * "courant" du switcher de session n'a aucun rapport avec celui de la
 * cible traitée.
 */
class EstablishmentUserPivotPolicy
{
    /**
     * Accès à la fiche personnel : le membre concerné (lecture/édition de
     * son identité) OU un admin ayant les droits staff.update (édition
     * complète, y compris les données professionnelles).
     */
    public function view(User $user, EstablishmentUserPivot $target): bool
    {
        return $target->user_id === $user->id
            || ($user->isLocalAdminOf($target->establishment)
                && RolePermissions::can($user->roleFor($target->establishment_id), 'staff.update'));
    }

    public function update(User $user, EstablishmentUserPivot $target): bool
    {
        return $user->isLocalAdminOf($target->establishment)
            && RolePermissions::can($user->roleFor($target->establishment_id), 'staff.update');
    }

    public function delete(User $user, EstablishmentUserPivot $target): bool
    {
        return $user->isGeneralAdminOf($target->establishment)
            && RolePermissions::can($user->roleFor($target->establishment_id), 'staff.delete');
    }
}
