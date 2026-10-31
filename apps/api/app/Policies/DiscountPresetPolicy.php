<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Billing\Models\DiscountPreset;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class DiscountPresetPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'discount_presets.manage');
    }

    public function create(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'discount_presets.manage');
    }

    public function update(User $user, DiscountPreset $discountPreset): bool
    {
        return $this->belongsToSameEstablishment($user, $discountPreset->establishment_id)
            && RolePermissions::can($user->currentRole(), 'discount_presets.manage');
    }
}
