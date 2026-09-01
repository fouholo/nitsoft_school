<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\Level;
use App\Models\User;

/**
 * Table de référence globale (comme Domain/Subject) : provisionnée
 * uniquement par le Super Admin SaaS via le bypass Gate::before
 * (AppServiceProvider) — tout le monde d'autre est refusé explicitement.
 */
class LevelPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Level $level): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Level $level): bool
    {
        return false;
    }

    public function delete(User $user, Level $level): bool
    {
        return false;
    }
}
