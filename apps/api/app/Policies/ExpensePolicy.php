<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\Expense;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class ExpensePolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'finance.access');
    }

    public function view(User $user, Expense $expense): bool
    {
        if (! $this->belongsToSameEstablishment($user, $expense->establishment_id)
            || ! RolePermissions::can($user->currentRole(), 'finance.access')) {
            return false;
        }

        if (RolePermissions::can($user->currentRole(), 'finance.scope_own_only')) {
            return $this->ownedByCurrentUser($user, $expense);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'expenses.create');
    }

    /**
     * Pas de mise à jour : comme les paiements, une dépense mal saisie se
     * supprime et se ressaisit plutôt que d'être modifiée.
     */
    public function delete(User $user, Expense $expense): bool
    {
        return $this->belongsToSameEstablishment($user, $expense->establishment_id)
            && RolePermissions::can($user->currentRole(), 'expenses.delete');
    }
}
