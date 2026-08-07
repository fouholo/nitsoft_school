<?php

declare(strict_types=1);

use App\Domain\Attendance\Events\StudentMarkedAbsent;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Enums\GuardianRelationship;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Notifications\Contracts\SmsProviderInterface;
use App\Domain\Notifications\Jobs\SendSmsJob;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\ValueObjects\SmsSendResult;
use Illuminate\Support\Facades\Queue;

function markAbsent(Establishment $establishment, Student $student): AttendanceRecord
{
    $session = AttendanceSession::factory()->create(['establishment_id' => $establishment->id]);

    return AttendanceRecord::factory()->create([
        'establishment_id' => $establishment->id,
        'attendance_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'absent',
    ]);
}

test('le contact principal approuvé et joignable reçoit un SMS unique', function () {
    Queue::fake();

    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $primary = Guardian::factory()->create(['phone' => '+2250700000000']);
    $other = Guardian::factory()->create(['phone' => '+2250700000001']);

    $student->guardians()->attach([
        $primary->id => [
            'establishment_id' => $establishment->id,
            'status' => GuardianLinkStatus::Approved,
            'relationship' => GuardianRelationship::Mere,
            'is_primary_contact' => true,
        ],
        $other->id => [
            'establishment_id' => $establishment->id,
            'status' => GuardianLinkStatus::Approved,
            'relationship' => GuardianRelationship::Pere,
            'is_primary_contact' => false,
        ],
    ]);

    StudentMarkedAbsent::dispatch(markAbsent($establishment, $student));

    $smsMessage = SmsMessage::sole();

    expect($smsMessage->guardian_id)->toBe($primary->id)
        ->and($smsMessage->status)->toBe('queued')
        ->and($smsMessage->body_rendered)->toContain($student->first_name);

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job) => $job->smsMessageId === $smsMessage->id);
});

test('un contact principal encore en attente ne reçoit aucun SMS', function () {
    Queue::fake();

    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $guardian = Guardian::factory()->create(['phone' => '+2250700000000']);

    $student->guardians()->attach($guardian->id, [
        'establishment_id' => $establishment->id,
        'status' => GuardianLinkStatus::Pending,
        'relationship' => GuardianRelationship::Mere,
        'is_primary_contact' => true,
    ]);

    StudentMarkedAbsent::dispatch(markAbsent($establishment, $student));

    expect(SmsMessage::count())->toBe(0);
    Queue::assertNotPushed(SendSmsJob::class);
});

test('un tuteur approuvé mais pas principal ne reçoit aucun SMS', function () {
    Queue::fake();

    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $guardian = Guardian::factory()->create(['phone' => '+2250700000000']);

    $student->guardians()->attach($guardian->id, [
        'establishment_id' => $establishment->id,
        'status' => GuardianLinkStatus::Approved,
        'relationship' => GuardianRelationship::Mere,
        'is_primary_contact' => false,
    ]);

    StudentMarkedAbsent::dispatch(markAbsent($establishment, $student));

    expect(SmsMessage::count())->toBe(0);
    Queue::assertNotPushed(SendSmsJob::class);
});

test('aucun contact principal désigné ne déclenche aucun SMS', function () {
    Queue::fake();

    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    StudentMarkedAbsent::dispatch(markAbsent($establishment, $student));

    expect(SmsMessage::count())->toBe(0);
    Queue::assertNotPushed(SendSmsJob::class);
});

test('SendSmsJob marque le message comme envoyé via le provider configuré', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $guardian = Guardian::factory()->create(['phone' => '+2250700000000']);

    $smsMessage = SmsMessage::create([
        'establishment_id' => $establishment->id,
        'guardian_id' => $guardian->id,
        'phone' => $guardian->phone,
        'body_rendered' => 'Test',
        'status' => 'queued',
    ]);

    $this->mock(SmsProviderInterface::class, function ($mock) {
        $mock->shouldReceive('send')->once()->andReturn(new SmsSendResult(success: true, providerMessageId: 'abc-123'));
    });

    (new SendSmsJob($smsMessage->id, $establishment->id))->handle(app(SmsProviderInterface::class));

    $smsMessage->refresh();

    expect($smsMessage->status)->toBe('sent')
        ->and($smsMessage->provider_message_id)->toBe('abc-123');
});
