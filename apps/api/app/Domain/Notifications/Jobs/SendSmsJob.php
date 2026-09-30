<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Contracts\SmsProviderInterface;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\Support\PhoneNumberNormalizer;
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
        // En mode after_response, le job tourne dans le processus de la
        // requête web : on restaure l'établissement courant de la requête
        // une fois le SMS traité.
        $previousEstablishmentId = app()->bound('currentEstablishmentId') ? app('currentEstablishmentId') : null;
        app()->instance('currentEstablishmentId', $this->establishmentId);

        try {
            $this->process($provider);
        } finally {
            if ($previousEstablishmentId !== null) {
                app()->instance('currentEstablishmentId', $previousEstablishmentId);
            } else {
                app()->forgetInstance('currentEstablishmentId');
            }
        }
    }

    private function process(SmsProviderInterface $provider): void
    {
        $message = SmsMessage::find($this->smsMessageId);

        if ($message === null || $message->status !== 'queued') {
            // Idempotence : déjà traité (ou rejoué après un job dupliqué).
            return;
        }

        $phone = PhoneNumberNormalizer::toE164((string) $message->phone);

        if ($phone === null) {
            $message->update([
                'status' => 'failed',
                'provider' => config('sms.default'),
                'error_message' => __('Numéro de téléphone invalide.'),
            ]);

            return;
        }

        $result = $provider->send($phone, $message->body_rendered);

        // Échec passager : le SMS reste en attente, avec la raison, pour être
        // renvoyé depuis l'écran SMS (ou par une nouvelle tentative de la file).
        $status = match (true) {
            $result->success => 'sent',
            $result->retryable => 'queued',
            default => 'failed',
        };

        $message->update([
            'status' => $status,
            'provider' => config('sms.default'),
            'provider_message_id' => $result->providerMessageId,
            'error_message' => $result->errorMessage,
            'sent_at' => $result->success ? now() : null,
        ]);

        // Sur une vraie file (sms.dispatch = queue), on laisse la file
        // retenter plus tard ; en after_response il n'y a pas de job de file
        // et c'est le bouton de renvoi qui prend le relais.
        if ($status === 'queued' && $this->job !== null && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 900);
        }
    }
}
