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
        return RolePermissions::can($user->currentRole(), 'billing.manage');
    }

    public function update(User $user, Discount $discount): bool
    {
        return $this->belongsToSameEstablishment($user, $discount->establishment_id)
            && RolePermissions::can($user->currentRole(), 'billing.manage');
    }

    public function delete(User $user, Discount $discount): bool
    {
        return $this->update($user, $discount);
    }
}
