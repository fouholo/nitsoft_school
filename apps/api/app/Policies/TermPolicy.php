<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\Term;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class TermPolicy
{
    use ChecksEstablishmentMembership;

    /**
     * La notion de période ne s'applique qu'au secondaire — au
     * préscolaire/primaire, les évaluations sont rattachées directement à un
     * numéro de composition (cf.
     * docs/superpowers/specs/2026-08-14-primaire-compositions-sans-periode-design.md).
     */
    public function viewAny(User $user): bool
    {
        if (! $this->isMemberOfCurrentEstablishment($user)) {
            return false;
        }

        $establishment = Establishment::find((int) app('currentEstablishmentId'));

        return $establishment?->isSecondaire() ?? false;
    }

    public function view(User $user, Term $term): bool
    {
        return $this->belongsToSameEstablishment($user, $term->establishment_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function update(User $user, Term $term): bool
    {
        return $this->belongsToSameEstablishment($user, $term->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }

    public function delete(User $user, Term $term): bool
    {
        return $this->update($user, $term);
    }
}
