<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class PaymentPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isBillingManagerOfCurrentEstablishment($user);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->belongsToSameEstablishment($user, $payment->establishment_id)
            && $this->isBillingManagerOfCurrentEstablishment($user);
    }

    public function create(User $user): bool
    {
        return $this->isBillingManagerOfCurrentEstablishment($user);
    }

    /**
     * Pas de mise à jour : un paiement enregistré est immuable (intégrité de
     * l'audit financier). Une correction passe par une suppression suivie
     * d'une nouvelle saisie, réservée à l'Admin (pas au Comptable).
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $this->belongsToSameEstablishment($user, $payment->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }
}
