<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\PrimarySubject;
use App\Models\User;

/**
 * Table de référence globale (comme Subject/Level/Domain) : provisionnée
 * uniquement par le Super Admin SaaS via le bypass Gate::before
 * (AppServiceProvider) — tout le monde d'autre est refusé explicitement.
 */
class PrimarySubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, PrimarySubject $primarySubject): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PrimarySubject $primarySubject): bool
    {
        return false;
    }

    public function delete(User $user, PrimarySubject $primarySubject): bool
    {
        return false;
    }
}
