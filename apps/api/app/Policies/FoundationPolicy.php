<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Models\Foundation;
use App\Models\User;

/**
 * Provisionnement SaaS (groupes scolaires) : réservé au Super Admin SaaS,
 * qui contourne cette Policy via Gate::before (AppServiceProvider). Tout le
 * monde d'autre est refusé explicitement — pas de gestion en libre-service
 * pour les établissements/fondateurs eux-mêmes dans ce MVP.
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
}
