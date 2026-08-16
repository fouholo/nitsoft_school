<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Grading\Models\AppreciationScale;
use App\Models\User;

/**
 * Table de référence globale (comme PrimarySubject/Subject/Level/Domain) :
 * provisionnée uniquement par le Super Admin SaaS via le bypass
 * Gate::before (AppServiceProvider) — tout le monde d'autre est refusé
 * explicitement.
 */
class AppreciationScalePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, AppreciationScale $appreciationScale): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AppreciationScale $appreciationScale): bool
    {
        return false;
    }

    public function delete(User $user, AppreciationScale $appreciationScale): bool
    {
        return false;
    }
}
