<?php

declare(strict_types=1);

namespace App\Domain\Notifications\ValueObjects;

final class SmsSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $errorMessage = null,
    ) {}
}
