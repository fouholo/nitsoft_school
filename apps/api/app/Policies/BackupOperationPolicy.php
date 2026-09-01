<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * export : couvert par le bypass Gate::before pour tout SaaS admin actif
 * (style GeneralInformationPolicy) — retourne false ici par défaut explicite.
 * wipe/import : opérations destructives, réservées au SaaS admin Principal
 * (style SaasAdminPolicy) — le carve-out dans AppServiceProvider::boot()
 * exclut ces deux abilities du bypass global pour laisser cette policy
 * trancher.
 */
class BackupOperationPolicy
{
    public function export(User $user): bool
    {
        return false;
    }

    public function wipe(User $user): bool
    {
        return $user->isMainSaasAdmin();
    }

    public function import(User $user): bool
    {
        return $user->isMainSaasAdmin();
    }
}
