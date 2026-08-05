<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\Term;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class TermPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
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
