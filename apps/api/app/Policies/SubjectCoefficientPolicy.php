<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\SubjectCoefficient;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class SubjectCoefficientPolicy
{
    use ChecksEstablishmentMembership;

    /**
     * Réservé au secondaire — au préscolaire/primaire, les coefficients par
     * matière sont portés par PrimarySubject (catalogue global SaaS), cf.
     * docs/superpowers/specs/2026-08-15-matieres-primaire-catalogue-coefficients-design.md.
     */
    public function viewAny(User $user): bool
    {
        if (! $this->isMemberOfCurrentEstablishment($user)) {
            return false;
        }

        $establishment = Establishment::find((int) app('currentEstablishmentId'));

        return $establishment?->isSecondaire() ?? false;
    }

    public function view(User $user, SubjectCoefficient $subjectCoefficient): bool
    {
        return $this->belongsToSameEstablishment($user, $subjectCoefficient->establishment_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function update(User $user, SubjectCoefficient $subjectCoefficient): bool
    {
        return $this->belongsToSameEstablishment($user, $subjectCoefficient->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }

    public function delete(User $user, SubjectCoefficient $subjectCoefficient): bool
    {
        return $this->update($user, $subjectCoefficient);
    }
}
