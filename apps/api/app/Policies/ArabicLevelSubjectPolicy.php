<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Matières arabes par niveau, propres à chaque école franco-arabe : elles
 * donnent la liste des compositions du primaire et l'ordre du bulletin du
 * secondaire.
 */
class ArabicLevelSubjectPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user)
            && $this->currentEstablishmentIsArabe()
            && ! in_array($user->currentRole(), ['caissier', 'parent'], true);
    }

    public function manage(User $user): bool
    {
        return $this->viewAny($user)
            && in_array($user->currentRole(), ['fondateur', 'directeur', 'gestionnaire'], true);
    }
}
