<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\Discount;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class DiscountPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'finance.access');
    }

    public function view(User $user, Discount $discount): bool
    {
        return $this->belongsToSameEstablishment($user, $discount->establishment_id)
            && RolePermissions::can($user->currentRole(), 'finance.access');
    }

    public function create(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'discounts.apply_preset');
    }

    /**
     * Saisie libre d'un type et d'une valeur, hors du catalogue.
     */
    public function freeEntry(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'discounts.free_entry');
    }

    public function update(User $user, Discount $discount): bool
    {
        $ability = $discount->discount_preset_id !== null ? 'discounts.apply_preset' : 'discounts.free_entry';

        return $this->belongsToSameEstablishment($user, $discount->establishment_id)
            && RolePermissions::can($user->currentRole(), $ability);
    }

    public function delete(User $user, Discount $discount): bool
    {
        return $this->update($user, $discount);
    }
}
