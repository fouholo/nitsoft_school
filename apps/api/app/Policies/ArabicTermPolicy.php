<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

/**
 * Réservé aux établissements is_arabe. Contrairement à TermPolicy (qui
 * exclut le préscolaire/primaire), ArabicTerm couvre tous les cycles via un
 * seul modèle — pas de restriction de cycle sur viewAny. Gère les rôles via
 * currentRole() plutôt qu'isAdminOfCurrentEstablishment() pour éviter la
 * lacune fondateur-établissement-indépendant — voir
 * ArabicSubjectCoefficientPolicy.
 */
class ArabicTermPolicy
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

    public function view(User $user, ArabicTerm $arabicTerm): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicTerm->establishment_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->currentRole(), ['fondateur', 'directeur', 'gestionnaire'], true);
    }

    public function update(User $user, ArabicTerm $arabicTerm): bool
    {
        return $this->belongsToSameEstablishment($user, $arabicTerm->establishment_id)
            && in_array($user->currentRole(), ['fondateur', 'directeur', 'gestionnaire'], true);
    }

    public function delete(User $user, ArabicTerm $arabicTerm): bool
    {
        return $this->update($user, $arabicTerm);
    }
}
