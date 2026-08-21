<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Arabic\Models\ArabicReportCard;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Réservé aux établissements is_arabe. Génération réservée à
 * fondateur/directeur/gestionnaire (via currentRole(), pas
 * isAdminOfCurrentEstablishment() — lacune fondateur-établissement-
 * indépendant déjà connue) + educateur, comme ReportCardPolicy côté
 * français (RolePermissions::MATRIX['report_cards.generate']).
 */
class ArabicReportCardPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        if (! $this->isMemberOfCurrentEstablishment($user)) {
            return false;
        }

        $establishment = Establishment::find((int) app('currentEstablishmentId'));

        return $establishment !== null && $establishment->is_arabe;
    }

    public function view(User $user, ArabicReportCard $arabicReportCard): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicReportCard->establishment_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->currentRole(), ['fondateur', 'directeur', 'gestionnaire'], true)
            || RolePermissions::can($user->currentRole(), 'report_cards.generate');
    }
}
