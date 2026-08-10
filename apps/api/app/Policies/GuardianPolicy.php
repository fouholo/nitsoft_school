<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Guardian;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class GuardianPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user) && $user->currentRole() !== 'caissier';
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $this->isMemberOfCurrentEstablishment($user)
            && $user->currentRole() !== 'caissier'
            && $this->hasApprovedLinkInCurrentEstablishment($guardian);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $this->isAdminOfCurrentEstablishment($user) && $this->hasApprovedLinkInCurrentEstablishment($guardian);
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $this->update($user, $guardian);
    }

    /**
     * `Guardian` n'est plus rattaché à un seul établissement (un parent peut
     * avoir des enfants dans des établissements différents) — l'appartenance
     * se vérifie désormais via un lien approuvé sur guardian_student, qui
     * porte establishment_id par ligne.
     */
    private function hasApprovedLinkInCurrentEstablishment(Guardian $guardian): bool
    {
        if (! app()->bound('currentEstablishmentId')) {
            return false;
        }

        return $guardian->students()
            ->wherePivot('establishment_id', app('currentEstablishmentId'))
            ->wherePivot('status', GuardianLinkStatus::Approved)
            ->exists();
    }
}
