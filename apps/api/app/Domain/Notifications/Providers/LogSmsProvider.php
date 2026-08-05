<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Providers;

use App\Domain\Notifications\Contracts\SmsProviderInterface;
use App\Domain\Notifications\ValueObjects\SmsSendResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Provider par défaut pour le développement local : écrit le SMS dans les
 * logs au lieu d'appeler une API tierce. À remplacer par un provider réel
 * (Twilio, Africa's Talking, Orange SMS API...) une fois le pays cible et
 * le contrat confirmés avec le client — voir plan d'architecture, section 6
 * et le risque "spike technique provider SMS" en section 8.
 */
class LogSmsProvider implements SmsProviderInterface
{
    public function send(string $toPhoneE164, string $body): SmsSendResult
    {
        $providerMessageId = (string) Str::uuid();

        Log::info('[SMS:log-provider] Envoi simulé', [
            'to' => $toPhoneE164,
            'body' => $body,
            'provider_message_id' => $providerMessageId,
        ]);

        return new SmsSendResult(success: true, providerMessageId: $providerMessageId);
    }
}
