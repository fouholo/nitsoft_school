<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Arabic\Models\ArabicSerie;
use App\Models\User;

/**
 * Table de référence globale (comme Subject) : provisionnée uniquement par
 * le Super Admin SaaS via le bypass Gate::before (AppServiceProvider) —
 * tout le monde d'autre est refusé explicitement.
 */
class ArabicSeriePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ArabicSerie $arabicSerie): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ArabicSerie $arabicSerie): bool
    {
        return false;
    }

    public function delete(User $user, ArabicSerie $arabicSerie): bool
    {
        return false;
    }
}
