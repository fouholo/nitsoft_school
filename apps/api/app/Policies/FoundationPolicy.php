<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Models\User;

/**
 * Provisionnement SaaS de la fondation elle-même (créer/renommer/rattacher
 * des établissements) : réservé au Super Admin SaaS, qui contourne cette
 * Policy via Gate::before (AppServiceProvider). Tout le monde d'autre est
 * refusé explicitement sur ces actions-là.
 *
 * La gestion du roster staff de la fondation (manageOrganization/
 * reclaimGeneralAdmin) est un pouvoir distinct, borné à la fondation, voir
 * User::isGeneralAdminOf() — pas un bypass SaaS.
 */
class FoundationPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Foundation $foundation): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Foundation $foundation): bool
    {
        return false;
    }

    public function delete(User $user, Foundation $foundation): bool
    {
        return false;
    }

    public function manageOrganization(User $user, Foundation $foundation): bool
    {
        return $user->isGeneralAdminOf($foundation);
    }

    public function reclaimGeneralAdmin(User $user, Foundation $foundation): bool
    {
        return FoundationUserPivot::where('foundation_id', $foundation->id)
            ->where('user_id', $user->id)
            ->where('role', 'fondateur')
            ->where('is_active', true)
            ->exists();
    }
}
