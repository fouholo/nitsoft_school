<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Notifications\Models\SmsMessage;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class SmsMessagePolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function view(User $user, SmsMessage $smsMessage): bool
    {
        return $this->belongsToSameEstablishment($user, $smsMessage->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }
}
