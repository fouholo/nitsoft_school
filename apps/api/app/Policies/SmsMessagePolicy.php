<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Establishments\Support\RolePermissions;
use App\Domain\Notifications\Models\SmsMessage;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class SmsMessagePolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user)
            || RolePermissions::can($user->currentRole(), 'guardians.notify');
    }

    public function view(User $user, SmsMessage $smsMessage): bool
    {
        return $this->belongsToSameEstablishment($user, $smsMessage->establishment_id)
            && ($this->isAdminOfCurrentEstablishment($user) || RolePermissions::can($user->currentRole(), 'guardians.notify'));
    }

    public function create(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'guardians.notify');
    }
}
