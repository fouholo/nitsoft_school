<?php

declare(strict_types=1);

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Notifications\Jobs\SendSmsJob;
use App\Domain\Notifications\Models\SmsMessage;
use App\Livewire\Notifications\SmsMessages\Send;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('un caissier envoie un SMS à tous les tuteurs approuvés d’un élève', function () {
    Queue::fake();

    $establishment = Establishment::factory()->create();
    $cashier = createUserWithRole($establishment, 'caissier');
    actingInEstablishment($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $approved = Guardian::factory()->create(['phone' => '+2250700000000']);
    $pending = Guardian::factory()->create(['phone' => '+2250700000001']);

    $student->guardians()->attach([
        $approved->id => ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Approved],
        $pending->id => ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Pending],
    ]);

    test()->actingAs($cashier);

    Livewire::test(Send::class)
        ->set('student_id', $student->id)
        ->set('body', 'Merci de régulariser les frais de scolarité.')
        ->call('send');

    $smsMessage = SmsMessage::sole();

    expect($smsMessage->guardian_id)->toBe($approved->id)
        ->and($smsMessage->status)->toBe('queued');

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job) => $job->smsMessageId === $smsMessage->id);
});

test('un enseignant n’a pas accès à l’écran d’envoi de SMS', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);
    test()->actingAs($teacher);

    Livewire::test(Send::class)->assertForbidden();
});

test('un directeur n’a pas accès à l’écran d’envoi de SMS (réservé au caissier)', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire::test(Send::class)->assertForbidden();
});
