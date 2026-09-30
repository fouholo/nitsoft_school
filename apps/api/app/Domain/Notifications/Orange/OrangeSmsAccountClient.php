<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Orange;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Lecture du solde de SMS (contrats) du compte Orange de la plateforme.
 * Mis en cache quelques minutes : l'écran SMS ne doit pas appeler Orange à
 * chaque rendu.
 */
class OrangeSmsAccountClient
{
    private const CACHE_KEY = 'orange_sms.contracts';

    private const CACHE_TTL = 300;

    public function __construct(private readonly OrangeTokenProvider $tokens) {}

    /**
     * @return list<array<string, mixed>>
     *
     * @throws OrangeSmsException
     */
    public function contracts(): array
    {
        /** @var list<array<string, mixed>> */
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): array => $this->fetchContracts());
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws OrangeSmsException
     */
    public function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);

        return $this->contracts();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchContracts(): array
    {
        try {
            $response = $this->request($this->tokens->token());

            if ($response->status() === 401) {
                $this->tokens->forget();
                $response = $this->request($this->tokens->token());
            }
        } catch (ConnectionException $e) {
            throw new OrangeSmsException(__('Orange SMS est injoignable.'), previous: $e);
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new OrangeSmsException(__('Orange a refusé la lecture du solde (HTTP :status).', ['status' => $response->status()]));
        }

        return array_values(array_filter($response->json(), 'is_array'));
    }

    private function request(string $token): Response
    {
        return Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('sms.orange.timeout'))
            ->get(rtrim((string) config('sms.orange.base_url'), '/').'/sms/admin/v1/contracts', [
                'country' => config('sms.orange.country'),
            ]);
    }
}
