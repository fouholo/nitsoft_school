<?php

declare(strict_types=1);

use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Notifications\Jobs\SendSmsJob;
use App\Domain\Notifications\Models\SmsMessage;
use App\Livewire\Notifications\SmsOverview;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const OVERVIEW_TOKEN_URL = 'https://api.orange.com/oauth/v3/token';
const OVERVIEW_CONTRACTS_URL = 'https://api.orange.com/sms/admin/v1/contracts*';

function smsFor(Establishment $establishment, string $status, ?string $createdAt = null): SmsMessage
{
    $sms = SmsMessage::withoutGlobalScopes()->create([
        'establishment_id' => $establishment->id,
        'guardian_id' => Guardian::factory()->create()->id,
        'phone' => '0700000000',
        'body_rendered' => 'Bonjour',
        'status' => $status,
    ]);

    if ($createdAt !== null) {
        $sms->forceFill(['created_at' => $createdAt])->saveQuietly();
    }

    return $sms;
}

function orangeContract(int $units, string $status = 'ACTIVE', string $expiration = '2099-01-01T00:00:00Z'): array
{
    return [
        'id' => 'c1', 'country' => 'CIV', 'offerName' => 'SMS_OCB',
        'availableUnits' => $units, 'status' => $status, 'expirationDate' => $expiration,
    ];
}

beforeEach(function () {
    config()->set('sms.orange.client_id', 'client-id');
    config()->set('sms.orange.client_secret', 'client-secret');
    config()->set('sms.orange.low_balance_threshold', 100);
});

test('l’écran est réservé aux administrateurs SaaS', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $this->actingAs(createGeneralAdmin($establishment));

    Livewire::test(SmsOverview::class)->assertForbidden();
});

test('avec le fournisseur de test, aucun solde n’est demandé à Orange', function () {
    config()->set('sms.default', 'log');
    Http::fake();
    $this->actingAs(createSaasAdmin('second'));

    Livewire::test(SmsOverview::class)
        ->assertSee(__('Solde Orange'))
        ->assertSee('SMS_PROVIDER=orange');

    Http::assertNothingSent();
});

test('le solde Orange est affiché, avec une alerte quand il est bas', function (int $units, bool $alert) {
    config()->set('sms.default', 'orange');
    Http::fake([
        OVERVIEW_TOKEN_URL => Http::response(['access_token' => 'jeton', 'expires_in' => '3600']),
        OVERVIEW_CONTRACTS_URL => Http::response([orangeContract($units)]),
    ]);
    $this->actingAs(createSaasAdmin('main'));

    $component = Livewire::test(SmsOverview::class)->assertSee(number_format($units, 0, ',', ' '))->assertSee('SMS_OCB');

    $alert
        ? $component->assertSee(__('Solde bas', []), false)
        : $component->assertDontSee(__('Solde bas', []), false);
})->with([
    'solde confortable' => [5000, false],
    'solde bas' => [20, true],
]);

test('un contrat expiré déclenche l’alerte même avec des unités', function () {
    config()->set('sms.default', 'orange');
    Http::fake([
        OVERVIEW_TOKEN_URL => Http::response(['access_token' => 'jeton', 'expires_in' => '3600']),
        OVERVIEW_CONTRACTS_URL => Http::response([orangeContract(5000, 'ACTIVE', '2020-01-01T00:00:00Z')]),
    ]);
    $this->actingAs(createSaasAdmin('main'));

    Livewire::test(SmsOverview::class)->assertSee(__('Solde bas', []), false)->assertSee(__('Inactif'));
});

test('une erreur d’Orange est affichée sans bloquer l’écran', function () {
    config()->set('sms.default', 'orange');
    Http::fake([
        OVERVIEW_TOKEN_URL => Http::response(['access_token' => 'jeton', 'expires_in' => '3600']),
        OVERVIEW_CONTRACTS_URL => Http::response([], 503),
    ]);
    $this->actingAs(createSaasAdmin('main'));

    Livewire::test(SmsOverview::class)
        ->assertOk()
        ->assertSee(__('Solde indisponible : :error', ['error' => __('Orange a refusé la lecture du solde (HTTP :status).', ['status' => 503])]))
        ->assertSee(__('Consommation par école'));
});

test('la consommation est comptée par école sur le mois choisi', function () {
    config()->set('sms.default', 'log');
    $alpha = Establishment::factory()->create(['name' => 'École Alpha']);
    $beta = Establishment::factory()->create(['name' => 'École Beta']);

    smsFor($alpha, 'sent', '2026-09-10 08:00:00');
    smsFor($alpha, 'sent', '2026-09-11 08:00:00');
    smsFor($alpha, 'failed', '2026-09-12 08:00:00');
    smsFor($beta, 'queued', '2026-09-13 08:00:00');
    smsFor($beta, 'sent', '2026-08-30 08:00:00');

    $this->actingAs(createSaasAdmin('main'));

    $component = Livewire::test(SmsOverview::class)->set('month', '2026-09');

    $rows = $component->viewData('consumption');

    expect($rows->pluck('name')->all())->toBe(['École Alpha', 'École Beta'])
        ->and($rows->firstWhere('name', 'École Alpha'))->toMatchArray(['sent' => 2, 'failed' => 1, 'queued' => 0])
        ->and($rows->firstWhere('name', 'École Beta'))->toMatchArray(['sent' => 0, 'failed' => 0, 'queued' => 1]);
});

test('le bouton de renvoi relance tous les SMS en attente, toutes écoles confondues', function () {
    config()->set('sms.default', 'log');
    config()->set('sms.dispatch', 'after_response');
    Bus::fake();

    $first = smsFor(Establishment::factory()->create(), 'queued');
    $second = smsFor(Establishment::factory()->create(), 'queued');
    smsFor(Establishment::factory()->create(), 'sent');

    $this->actingAs(createSaasAdmin('main'));

    Livewire::test(SmsOverview::class)
        ->assertViewHas('pendingCount', 2)
        ->call('resendPending')
        ->assertSet('flash', __(':count SMS relancé(s). Ils partent dans quelques secondes.', ['count' => 2]));

    Bus::assertDispatchedAfterResponse(SendSmsJob::class, 2);
    Bus::assertDispatchedAfterResponse(SendSmsJob::class, fn (SendSmsJob $job) => $job->smsMessageId === $first->id);
    Bus::assertDispatchedAfterResponse(SendSmsJob::class, fn (SendSmsJob $job) => $job->smsMessageId === $second->id);
});
