<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class InvoicePolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'finance.access');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $this->belongsToSameEstablishment($user, $invoice->establishment_id)
            || ! RolePermissions::can($user->currentRole(), 'finance.access')) {
            return false;
        }

        if (RolePermissions::can($user->currentRole(), 'finance.scope_own_only')) {
            return $this->ownedByCurrentUser($user, $invoice);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'billing.manage');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->belongsToSameEstablishment($user, $invoice->establishment_id)
            && RolePermissions::can($user->currentRole(), 'billing.manage');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }
}
