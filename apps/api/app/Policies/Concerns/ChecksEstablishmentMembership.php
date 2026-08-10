<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Domain\Billing\Contracts\HasOwnerColumn;
use App\Models\User;

/**
 * Vérifications communes aux Policies par domaine : le Global Scope tenant
 * empêche déjà la fuite de données inter-établissements au niveau requête,
 * mais chaque Policy revérifie explicitement l'appartenance (défense en
 * profondeur — voir plan d'architecture, section 2.1 et 9).
 */
trait ChecksEstablishmentMembership
{
    protected function isMemberOfCurrentEstablishment(User $user): bool
    {
        if (! app()->bound('currentEstablishmentId')) {
            return false;
        }

        return $user->hasAccessTo((int) app('currentEstablishmentId'));
    }

    protected function isAdminOfCurrentEstablishment(User $user): bool
    {
        return $user->hasAdminRightsOnCurrentEstablishment();
    }

    protected function isLocalAdminOfCurrentEstablishment(User $user): bool
    {
        return $user->isLocalAdminOfCurrentEstablishment();
    }

    protected function belongsToSameEstablishment(User $user, int $modelEstablishmentId): bool
    {
        return app()->bound('currentEstablishmentId')
            && (int) app('currentEstablishmentId') === $modelEstablishmentId
            && $user->hasAccessTo($modelEstablishmentId);
    }

    /**
     * Portée "propres saisies uniquement" (éducateur sur les finances) — voir
     * App\Domain\Billing\Concerns\HasOwnerScope, appliqué en double avec le
     * filtrage de requête côté Livewire (défense en profondeur).
     */
    protected function ownedByCurrentUser(User $user, HasOwnerColumn $model): bool
    {
        $column = $model->ownerColumn();

        return (int) $model->{$column} === $user->id;
    }
}
