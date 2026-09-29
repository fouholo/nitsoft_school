<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Models\SchoolRegistration;
use App\Models\User;

/**
 * Validation des demandes d'inscription de fondateurs : tout administrateur
 * SaaS actif (Principal ou Secondaire), comme la création d'établissements.
 * Le Gate::before global accorde déjà ces abilities aux SaaS admins ; les
 * règles sont explicites ici pour ne pas en dépendre.
 */
class SchoolRegistrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSaasAdmin();
    }

    public function approve(User $user, SchoolRegistration $registration): bool
    {
        return $user->isSaasAdmin();
    }

    public function reject(User $user, SchoolRegistration $registration): bool
    {
        return $user->isSaasAdmin();
    }
}
