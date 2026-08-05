<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Contracts\SmsProviderInterface;
use App\Domain\Notifications\Models\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job dédié à la queue "sms" (isolée pour pouvoir prioriser/limiter le débit
 * indépendamment des autres jobs) — voir plan d'architecture, section 6.
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $smsMessageId,
        public readonly int $establishmentId,
    ) {
        $this->onQueue('sms');
    }

    public function handle(SmsProviderInterface $provider): void
    {
        app()->instance('currentEstablishmentId', $this->establishmentId);

        $message = SmsMessage::find($this->smsMessageId);

        if ($message === null || $message->status !== 'queued') {
            // Idempotence : déjà traité (ou rejoué après un job dupliqué).
            return;
        }

        $result = $provider->send($message->phone, $message->body_rendered);

        $message->update([
            'status' => $result->success ? 'sent' : 'failed',
            'provider' => config('sms.default'),
            'provider_message_id' => $result->providerMessageId,
            'error_message' => $result->errorMessage,
            'sent_at' => $result->success ? now() : null,
        ]);
    }
}
