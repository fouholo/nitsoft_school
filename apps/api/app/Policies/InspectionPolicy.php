<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Models\Inspection;
use App\Models\User;

/**
 * Table de référence globale (comme Foundation/Establishment) : provisionnée
 * uniquement par le Super Admin SaaS via le bypass Gate::before
 * (AppServiceProvider) — tout le monde d'autre est refusé explicitement.
 */
class InspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Inspection $inspection): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Inspection $inspection): bool
    {
        return false;
    }

    public function delete(User $user, Inspection $inspection): bool
    {
        return false;
    }
}
