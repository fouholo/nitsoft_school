<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\Payment;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class PaymentPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'finance.access');
    }

    public function view(User $user, Payment $payment): bool
    {
        if (! $this->belongsToSameEstablishment($user, $payment->establishment_id)
            || ! RolePermissions::can($user->currentRole(), 'finance.access')) {
            return false;
        }

        if (RolePermissions::can($user->currentRole(), 'finance.scope_own_only')) {
            return $this->ownedByCurrentUser($user, $payment);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'billing.manage');
    }

    /**
     * Pas de mise à jour : un paiement enregistré est immuable (intégrité de
     * l'audit financier). Une correction passe par une suppression suivie
     * d'une nouvelle saisie, réservée à fondateur/directeur.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $this->belongsToSameEstablishment($user, $payment->establishment_id)
            && RolePermissions::can($user->currentRole(), 'payments.delete');
    }
}
