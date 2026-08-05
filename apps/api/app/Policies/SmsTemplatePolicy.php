<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Notifications\Models\SmsTemplate;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class SmsTemplatePolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function view(User $user, SmsTemplate $smsTemplate): bool
    {
        return $this->belongsToSameEstablishment($user, $smsTemplate->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user);
    }

    public function update(User $user, SmsTemplate $smsTemplate): bool
    {
        return $this->belongsToSameEstablishment($user, $smsTemplate->establishment_id)
            && $this->isAdminOfCurrentEstablishment($user);
    }

    public function delete(User $user, SmsTemplate $smsTemplate): bool
    {
        return $this->update($user, $smsTemplate);
    }
}
