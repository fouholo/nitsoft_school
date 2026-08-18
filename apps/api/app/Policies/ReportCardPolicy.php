<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Support\RolePermissions;
use App\Domain\Grading\Models\ReportCard;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class ReportCardPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function view(User $user, ReportCard $reportCard): bool
    {
        return $this->belongsToSameEstablishment($user, $reportCard->establishment_id);
    }

    /**
     * Génération/régénération des bulletins réservée à l'Admin établissement
     * (agrégation administrative centralisée, cf. plan section 8, Phase 1),
     * ou à l'éducateur (cf. RolePermissions::MATRIX['report_cards.generate']).
     */
    public function create(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user)
            || RolePermissions::can($user->currentRole(), 'report_cards.generate');
    }
}
