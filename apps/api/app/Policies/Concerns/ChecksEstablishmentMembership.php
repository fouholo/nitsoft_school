<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

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

        return $user->belongsToEstablishment((int) app('currentEstablishmentId'));
    }

    protected function isAdminOfCurrentEstablishment(User $user): bool
    {
        if (! app()->bound('currentEstablishmentId')) {
            return false;
        }

        return $user->roleFor((int) app('currentEstablishmentId')) === 'admin';
    }

    protected function isBillingManagerOfCurrentEstablishment(User $user): bool
    {
        if (! app()->bound('currentEstablishmentId')) {
            return false;
        }

        return in_array($user->roleFor((int) app('currentEstablishmentId')), ['admin', 'accountant'], true);
    }

    protected function belongsToSameEstablishment(User $user, int $modelEstablishmentId): bool
    {
        return app()->bound('currentEstablishmentId')
            && (int) app('currentEstablishmentId') === $modelEstablishmentId
            && $user->belongsToEstablishment($modelEstablishmentId);
    }
}
