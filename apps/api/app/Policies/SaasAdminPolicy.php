<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Models\SaasAdmin;
use App\Models\User;

/**
 * Gestion du roster des admins SaaS : lecture couverte par le bypass
 * Gate::before (MAIN et SECOND) ; les actions mutantes sont explicitement
 * réservées à MAIN, Gate::before défère à cette Policy pour elles
 * (AppServiceProvider::boot()).
 */
class SaasAdminPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, SaasAdmin $target): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return $user->isMainSaasAdmin();
    }

    public function update(User $user, SaasAdmin $target): bool
    {
        return $user->isMainSaasAdmin();
    }

    public function delete(User $user, SaasAdmin $target): bool
    {
        return $user->isMainSaasAdmin();
    }
}
