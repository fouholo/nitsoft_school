<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class InvoicePolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isBillingManagerOfCurrentEstablishment($user);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->belongsToSameEstablishment($user, $invoice->establishment_id)
            && $this->isBillingManagerOfCurrentEstablishment($user);
    }

    public function create(User $user): bool
    {
        return $this->isBillingManagerOfCurrentEstablishment($user);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->belongsToSameEstablishment($user, $invoice->establishment_id)
            && $this->isBillingManagerOfCurrentEstablishment($user);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }
}
