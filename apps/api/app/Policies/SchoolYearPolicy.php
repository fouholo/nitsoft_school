<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\SchoolYear;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Référentiel partagé entre établissements (voir migration
 * 2026_08_20_010000_make_school_years_global) : tout membre d'un
 * établissement peut consulter la liste, mais la saisie (création,
 * modification, suppression) est réservée aux administrateurs SaaS — qui la
 * contournent de toute façon via Gate::before, donc ces méthodes renvoient
 * false pour tout le monde d'autre.
 */
class SchoolYearPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function view(User $user, SchoolYear $schoolYear): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SchoolYear $schoolYear): bool
    {
        return false;
    }

    public function delete(User $user, SchoolYear $schoolYear): bool
    {
        return false;
    }
}
