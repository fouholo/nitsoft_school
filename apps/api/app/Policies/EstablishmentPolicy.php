<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Models\User;

/**
 * Deux familles de pouvoirs distinctes sur ce modèle :
 * - Provisionnement de l'établissement lui-même. `create` (écran global
 *   `Livewire\Establishments\Index`) reste réservé au Super Admin SaaS
 *   (bypass Gate::before, AppServiceProvider) — la création par un fondateur
 *   au sein de sa propre fondation passe par `ManageOrganization::create()`
 *   sous l'ability `manageOrganization` (isGeneralAdminOf), pas par celle-ci
 *   — voir docs/superpowers/specs/2026-08-10-saisie-fondation-etablissements-design.md.
 *   `update`/`delete` restent gouvernés par le fondateur de la fondation
 *   concernée (User::isFounderOfEstablishment()), pour un futur écran
 *   d'édition/suppression.
 * - Gestion du roster staff d'un établissement déjà existant (manageStaff/
 *   manageOrganization/reclaimGeneralAdmin) — voir
 *   User::isLocalAdminOf()/isGeneralAdminOf() pour la définition du pouvoir.
 */
class EstablishmentPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Establishment $establishment): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Establishment $establishment): bool
    {
        return $establishment->foundation_id !== null && $user->isFounderOfEstablishment($establishment->id);
    }

    public function delete(User $user, Establishment $establishment): bool
    {
        return $this->update($user, $establishment);
    }

    public function manageStaff(User $user, Establishment $establishment): bool
    {
        return $user->isLocalAdminOf($establishment);
    }

    public function manageOrganization(User $user, Establishment $establishment): bool
    {
        return $user->isGeneralAdminOf($establishment);
    }

    public function reclaimGeneralAdmin(User $user, Establishment $establishment): bool
    {
        return EstablishmentUserPivot::where('establishment_id', $establishment->id)
            ->where('user_id', $user->id)
            ->where('role', 'fondateur')
            ->where('is_active', true)
            ->exists();
    }
}
