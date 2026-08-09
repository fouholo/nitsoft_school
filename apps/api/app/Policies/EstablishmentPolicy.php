<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Models\User;

/**
 * Gestion du roster staff d'un établissement (pas d'établissements eux-mêmes,
 * réservés au Super Admin SaaS via FoundationPolicy) — voir
 * User::isLocalAdminOf()/isGeneralAdminOf() pour la définition du pouvoir.
 */
class EstablishmentPolicy
{
    public function manageStaff(User $user, Establishment $establishment): bool
    {
        return $user->isLocalAdminOf($establishment);
    }

    public function manageOrganization(User $user, Establishment $establishment): bool
    {
        return $user->isGeneralAdminOf($establishment);
    }

    public function reclaimGeneralAdmin(User $user, Establishment $establishment): bool
    {
        return EstablishmentUserPivot::where('establishment_id', $establishment->id)
            ->where('user_id', $user->id)
            ->where('role', 'fondateur')
            ->where('is_active', true)
            ->exists();
    }
}
