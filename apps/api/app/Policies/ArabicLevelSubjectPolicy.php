<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Matières du primaire arabe, par niveau, propres à chaque école.
 */
class ArabicLevelSubjectPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user)
            && $this->currentEstablishmentIsArabicPrimary()
            && ! in_array($user->currentRole(), ['caissier', 'parent'], true);
    }

    public function manage(User $user): bool
    {
        return $this->viewAny($user)
            && in_array($user->currentRole(), ['fondateur', 'directeur', 'gestionnaire'], true);
    }
}
