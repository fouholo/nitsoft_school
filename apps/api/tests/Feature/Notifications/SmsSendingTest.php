<?php

declare(strict_types=1);

use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Notifications\Contracts\SmsProviderInterface;
use App\Domain\Notifications\Jobs\SendSmsJob;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\Services\SmsDispatcher;
use App\Domain\Notifications\ValueObjects\SmsSendResult;
use Illuminate\Support\Facades\Bus;

function queuedSms(Establishment $establishment, string $phone = '0700000000'): SmsMessage
{
    return SmsMessage::create([
        'establishment_id' => $establishment->id,
        'guardian_id' => Guardian::factory()->create(['phone' => $phone])->id,
        'phone' => $phone,
        'body_rendered' => 'Bonjour',
        'status' => 'queued',
    ]);
}

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    actingInEstablishment($this->establishment);
});

test('le numéro local est converti avant d’être confié au fournisseur', function () {
    $sms = queuedSms($this->establishment, '07 00 00 00 00');

    $this->mock(SmsProviderInterface::class, function ($mock) {
        $mock->shouldReceive('send')->once()->with('+2250700000000', 'Bonjour')
            ->andReturn(new SmsSendResult(success: true, providerMessageId: 'id-1'));
    });

    (new SendSmsJob($sms->id, $this->establishment->id))->handle(app(SmsProviderInterface::class));

    expect($sms->refresh()->status)->toBe('sent')
        ->and($sms->provider_message_id)->toBe('id-1')
        ->and($sms->sent_at)->not->toBeNull();
});

test('un numéro inexploitable passe en échec sans appeler le fournisseur', function () {
    $sms = queuedSms($this->establishment, '1234');

    $this->mock(SmsProviderInterface::class, fn ($mock) => $mock->shouldNotReceive('send'));

    (new SendSmsJob($sms->id, $this->establishment->id))->handle(app(SmsProviderInterface::class));

    expect($sms->refresh()->status)->toBe('failed')
        ->and($sms->error_message)->toBe(__('Numéro de téléphone invalide.'));
});

test('un échec définitif passe le SMS en échec', function () {
    $sms = queuedSms($this->establishment);

    $this->mock(SmsProviderInterface::class, fn ($mock) => $mock->shouldReceive('send')
        ->andReturn(new SmsSendResult(success: false, errorMessage: 'Refusé', retryable: false)));

    (new SendSmsJob($sms->id, $this->establishment->id))->handle(app(SmsProviderInterface::class));

    expect($sms->refresh()->status)->toBe('failed')->and($sms->error_message)->toBe('Refusé');
});

test('un échec passager laisse le SMS en attente avec sa raison', function () {
    $sms = queuedSms($this->establishment);

    $this->mock(SmsProviderInterface::class, fn ($mock) => $mock->shouldReceive('send')
        ->andReturn(new SmsSendResult(success: false, errorMessage: 'Forfait épuisé', retryable: true)));

    (new SendSmsJob($sms->id, $this->establishment->id))->handle(app(SmsProviderInterface::class));

    expect($sms->refresh()->status)->toBe('queued')
        ->and($sms->error_message)->toBe('Forfait épuisé')
        ->and($sms->sent_at)->toBeNull();
});

test('le job rétablit l’établissement courant de la requête', function () {
    $other = Establishment::factory()->create();
    actingInEstablishment($other);

    $sms = SmsMessage::withoutGlobalScopes()->create([
        'establishment_id' => $this->establishment->id,
        'guardian_id' => Guardian::factory()->create()->id,
        'phone' => '0700000000',
        'body_rendered' => 'Bonjour',
        'status' => 'queued',
    ]);

    $this->mock(SmsProviderInterface::class, fn ($mock) => $mock->shouldReceive('send')
        ->andReturn(new SmsSendResult(success: true)));

    (new SendSmsJob($sms->id, $this->establishment->id))->handle(app(SmsProviderInterface::class));

    expect(app('currentEstablishmentId'))->toBe($other->id);
});

test('par défaut, l’envoi part après la réponse HTTP', function () {
    config()->set('sms.dispatch', 'after_response');
    Bus::fake();

    app(SmsDispatcher::class)->dispatch(queuedSms($this->establishment));

    Bus::assertDispatchedAfterResponse(SendSmsJob::class);
});

test('en mode queue, l’envoi part dans la file', function () {
    config()->set('sms.dispatch', 'queue');
    Bus::fake();

    $sms = queuedSms($this->establishment);
    app(SmsDispatcher::class)->dispatch($sms);

    Bus::assertDispatched(SendSmsJob::class, fn (SendSmsJob $job) => $job->smsMessageId === $sms->id
        && $job->establishmentId === $this->establishment->id);
});
