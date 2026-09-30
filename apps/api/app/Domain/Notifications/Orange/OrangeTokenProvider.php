<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Orange;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Jeton OAuth 2.0 (client_credentials) de l'API Orange, valable une heure :
 * on le réutilise depuis le cache et on n'en redemande un qu'à expiration
 * ou après un refus 401 d'Orange (forget()).
 */
class OrangeTokenProvider
{
    private const CACHE_KEY = 'orange_sms.token';

    /** Marge avant l'expiration annoncée par Orange, en secondes. */
    private const EXPIRY_MARGIN = 300;

    /**
     * @throws OrangeSmsException
     */
    public function token(): string
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $clientId = (string) config('sms.orange.client_id');
        $clientSecret = (string) config('sms.orange.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new OrangeSmsException(__('Identifiants Orange SMS non configurés.'));
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withBasicAuth($clientId, $clientSecret)
                ->timeout((int) config('sms.orange.timeout'))
                ->post(rtrim((string) config('sms.orange.base_url'), '/').'/oauth/v3/token', [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (ConnectionException $e) {
            throw new OrangeSmsException(__('Orange SMS est injoignable.'), previous: $e);
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new OrangeSmsException(__('Orange a refusé la demande de jeton (HTTP :status).', ['status' => $response->status()]));
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600) - self::EXPIRY_MARGIN);
        Cache::put(self::CACHE_KEY, $token, $ttl);

        return $token;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
