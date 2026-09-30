<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Providers;

use App\Domain\Notifications\Contracts\SmsProviderInterface;
use App\Domain\Notifications\Orange\OrangeSmsException;
use App\Domain\Notifications\Orange\OrangeTokenProvider;
use App\Domain\Notifications\ValueObjects\SmsSendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Envoi réel via l'API SMS Orange Afrique et Moyen-Orient — voir
 * docs/superpowers/specs/2026-09-30-sms-orange-design.md pour la table de
 * traduction des réponses d'Orange en statut de SMS.
 */
class OrangeSmsProvider implements SmsProviderInterface
{
    public function __construct(private readonly OrangeTokenProvider $tokens) {}

    public function send(string $toPhoneE164, string $body): SmsSendResult
    {
        try {
            $response = $this->request($toPhoneE164, $body, $this->tokens->token());

            // Jeton expiré ou révoqué entre-temps : un seul renouvellement.
            if ($response->status() === 401) {
                $this->tokens->forget();
                $response = $this->request($toPhoneE164, $body, $this->tokens->token());
            }
        } catch (OrangeSmsException $e) {
            Log::warning('orange_sms.unavailable', ['error' => $e->getMessage()]);

            return new SmsSendResult(success: false, errorMessage: $e->getMessage(), retryable: true);
        } catch (ConnectionException) {
            Log::warning('orange_sms.unavailable', ['error' => 'connection']);

            return new SmsSendResult(success: false, errorMessage: __('Orange SMS est injoignable.'), retryable: true);
        } finally {
            $this->pause();
        }

        return $this->interpret($response);
    }

    private function request(string $toPhoneE164, string $body, string $token): Response
    {
        $senderAddress = (string) config('sms.orange.sender_address');
        $senderName = trim((string) config('sms.orange.sender_name'));

        $message = [
            'address' => 'tel:'.$toPhoneE164,
            'senderAddress' => $senderAddress,
            'outboundSMSTextMessage' => ['message' => $body],
        ];

        if ($senderName !== '') {
            $message['senderName'] = $senderName;
        }

        $url = rtrim((string) config('sms.orange.base_url'), '/')
            .'/smsmessaging/v1/outbound/'.rawurlencode($senderAddress).'/requests';

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('sms.orange.timeout'))
            ->post($url, ['outboundSMSMessageRequest' => $message]);
    }

    private function interpret(Response $response): SmsSendResult
    {
        $status = $response->status();

        if ($status === 201) {
            $resourceUrl = (string) $response->json('outboundSMSMessageRequest.resourceURL', '');

            return new SmsSendResult(
                success: true,
                providerMessageId: $resourceUrl !== '' ? Str::afterLast($resourceUrl, '/') : null,
            );
        }

        $error = __('Orange a refusé l\'envoi (HTTP :status) : :message', [
            'status' => $status,
            'message' => $this->errorText($response),
        ]);

        // 401 persistant (identifiants), 403 (forfait épuisé ou expiré),
        // 429 (débit) et 5xx : le SMS reste en attente pour être renvoyé une
        // fois le problème réglé. Le reste (400...) est un échec définitif.
        $retryable = in_array($status, [401, 403, 429], true) || $status >= 500;

        Log::warning('orange_sms.rejected', ['status' => $status, 'error' => $this->errorText($response)]);

        return new SmsSendResult(success: false, errorMessage: $error, retryable: $retryable);
    }

    private function errorText(Response $response): string
    {
        foreach ([
            'requestError.serviceException.text',
            'requestError.policyException.text',
            'description',
            'message',
        ] as $path) {
            $text = $response->json($path);

            if (is_string($text) && $text !== '') {
                return $text;
            }
        }

        return __('réponse inattendue');
    }

    private function pause(): void
    {
        $milliseconds = (int) config('sms.orange.pause_between_sends_ms');

        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
