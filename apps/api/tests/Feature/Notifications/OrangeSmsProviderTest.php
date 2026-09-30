<?php

declare(strict_types=1);

use App\Domain\Notifications\Orange\OrangeSmsException;
use App\Domain\Notifications\Orange\OrangeTokenProvider;
use App\Domain\Notifications\Providers\OrangeSmsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const ORANGE_TOKEN_URL = 'https://api.orange.com/oauth/v3/token';
const ORANGE_SEND_URL = 'https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests';

beforeEach(function () {
    config()->set('sms.orange.client_id', 'client-id');
    config()->set('sms.orange.client_secret', 'client-secret');
    config()->set('sms.orange.sender_name', null);
    config()->set('sms.orange.pause_between_sends_ms', 0);
});

function orangeTokenResponse(string $token = 'jeton-1'): array
{
    return ['token_type' => 'Bearer', 'access_token' => $token, 'expires_in' => '3600'];
}

function orangeSentResponse(): array
{
    return ['outboundSMSMessageRequest' => [
        'resourceURL' => 'https://api.orange.com/smsmessaging/v1/outbound/tel:+2250000/requests/0f1e2d3c-aaaa-bbbb-cccc-000000000001',
    ]];
}

test('le jeton est demandé en client_credentials avec authentification Basic, puis réutilisé', function () {
    Http::fake([ORANGE_TOKEN_URL => Http::response(orangeTokenResponse())]);

    $tokens = app(OrangeTokenProvider::class);

    expect($tokens->token())->toBe('jeton-1')
        ->and($tokens->token())->toBe('jeton-1');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === ORANGE_TOKEN_URL
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-id:client-secret'))
        && $request['grant_type'] === 'client_credentials');
});

test('sans identifiants, aucun appel n’est fait et l’erreur est réessayable', function () {
    config()->set('sms.orange.client_id', null);
    Http::fake();

    expect(fn () => app(OrangeTokenProvider::class)->token())->toThrow(OrangeSmsException::class);

    Http::assertNothingSent();
});

test('un envoi réussi respecte le format Orange et récupère l’identifiant du message', function () {
    Http::fake([
        ORANGE_TOKEN_URL => Http::response(orangeTokenResponse()),
        ORANGE_SEND_URL => Http::response(orangeSentResponse(), 201),
    ]);

    $result = app(OrangeSmsProvider::class)->send('+2250700000000', 'Bonjour');

    expect($result->success)->toBeTrue()
        ->and($result->providerMessageId)->toBe('0f1e2d3c-aaaa-bbbb-cccc-000000000001');

    Http::assertSent(fn (Request $request) => $request->url() === ORANGE_SEND_URL
        && $request->hasHeader('Authorization', 'Bearer jeton-1')
        && $request->data() === ['outboundSMSMessageRequest' => [
            'address' => 'tel:+2250700000000',
            'senderAddress' => 'tel:+2250000',
            'outboundSMSTextMessage' => ['message' => 'Bonjour'],
        ]]);
});

test('le nom d’expéditeur n’est envoyé que s’il est configuré', function () {
    config()->set('sms.orange.sender_name', 'NITSOFT');
    Http::fake([
        ORANGE_TOKEN_URL => Http::response(orangeTokenResponse()),
        ORANGE_SEND_URL => Http::response(orangeSentResponse(), 201),
    ]);

    app(OrangeSmsProvider::class)->send('+2250700000000', 'Bonjour');

    Http::assertSent(fn (Request $request) => $request->url() === ORANGE_SEND_URL
        && $request['outboundSMSMessageRequest']['senderName'] === 'NITSOFT');
});

test('un jeton refusé (401) est renouvelé une seule fois', function () {
    Cache::put('orange_sms.token', 'jeton-perime', 600);
    Http::fake([
        ORANGE_TOKEN_URL => Http::response(orangeTokenResponse('jeton-2')),
        ORANGE_SEND_URL => Http::sequence()
            ->push(['code' => 42, 'message' => 'Expired credentials'], 401)
            ->push(orangeSentResponse(), 201),
    ]);

    $result = app(OrangeSmsProvider::class)->send('+2250700000000', 'Bonjour');

    expect($result->success)->toBeTrue();
    Http::assertSent(fn (Request $request) => $request->url() === ORANGE_SEND_URL && $request->hasHeader('Authorization', 'Bearer jeton-2'));
});

test('les réponses d’Orange sont classées en succès, échec définitif ou échec réessayable', function (int $status, bool $retryable) {
    Http::fake([
        ORANGE_TOKEN_URL => Http::response(orangeTokenResponse()),
        ORANGE_SEND_URL => Http::response(['requestError' => ['serviceException' => ['text' => 'Motif Orange']]], $status),
    ]);

    $result = app(OrangeSmsProvider::class)->send('+2250700000000', 'Bonjour');

    expect($result->success)->toBeFalse()
        ->and($result->retryable)->toBe($retryable)
        ->and($result->errorMessage)->toContain('Motif Orange')
        ->and($result->errorMessage)->toContain((string) $status);
})->with([
    'requête refusée' => [400, false],
    'forfait épuisé' => [403, true],
    'débit dépassé' => [429, true],
    'panne Orange' => [503, true],
]);

test('un 401 qui persiste après renouvellement reste réessayable', function () {
    Http::fake([
        ORANGE_TOKEN_URL => Http::response(orangeTokenResponse()),
        ORANGE_SEND_URL => Http::response(['code' => 42, 'message' => 'Invalid credentials'], 401),
    ]);

    $result = app(OrangeSmsProvider::class)->send('+2250700000000', 'Bonjour');

    expect($result->success)->toBeFalse()->and($result->retryable)->toBeTrue();
});

test('un jeton impossible à obtenir laisse le SMS réessayable', function () {
    Http::fake([ORANGE_TOKEN_URL => Http::response(['error' => 'invalid_client'], 401)]);

    $result = app(OrangeSmsProvider::class)->send('+2250700000000', 'Bonjour');

    expect($result->success)->toBeFalse()->and($result->retryable)->toBeTrue();
});

test('Orange injoignable laisse le SMS réessayable', function () {
    Http::fake([
        ORANGE_TOKEN_URL => Http::response(orangeTokenResponse()),
        ORANGE_SEND_URL => fn () => throw new ConnectionException('timeout'),
    ]);

    $result = app(OrangeSmsProvider::class)->send('+2250700000000', 'Bonjour');

    expect($result->success)->toBeFalse()->and($result->retryable)->toBeTrue();
});
