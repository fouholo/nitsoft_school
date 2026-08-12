<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Models\GeneralInformation;
use App\Models\User;

/**
 * Réglage plateforme singleton (comme Inspection/Direction) : accessible
 * uniquement par le Super Admin SaaS via le bypass Gate::before
 * (AppServiceProvider) — tout le monde d'autre est refusé explicitement.
 */
class GeneralInformationPolicy
{
    public function view(User $user, GeneralInformation $generalInformation): bool
    {
        return false;
    }

    public function update(User $user, GeneralInformation $generalInformation): bool
    {
        return false;
    }
}
