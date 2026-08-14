<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\Domain;
use App\Models\User;

/**
 * Table de référence globale (comme Inspection/Direction) : provisionnée
 * uniquement par le Super Admin SaaS via le bypass Gate::before
 * (AppServiceProvider) — tout le monde d'autre est refusé explicitement.
 */
class DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Domain $domain): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Domain $domain): bool
    {
        return false;
    }

    public function delete(User $user, Domain $domain): bool
    {
        return false;
    }
}
