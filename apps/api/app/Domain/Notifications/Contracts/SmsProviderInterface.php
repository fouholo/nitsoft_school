<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\ValueObjects\SmsSendResult;

interface SmsProviderInterface
{
    public function send(string $toPhoneE164, string $body): SmsSendResult;
}
