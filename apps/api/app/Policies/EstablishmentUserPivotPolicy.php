<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Models\User;

/**
 * Actions sur une ligne staff existante (activer/désactiver/supprimer) —
 * la création passe par EstablishmentPolicy::manageStaff/manageOrganization
 * (pas de cible existante à ce stade).
 */
class EstablishmentUserPivotPolicy
{
    public function update(User $user, EstablishmentUserPivot $target): bool
    {
        return $user->isLocalAdminOf($target->establishment);
    }

    public function delete(User $user, EstablishmentUserPivot $target): bool
    {
        return $user->isGeneralAdminOf($target->establishment);
    }
}
