<?php

declare(strict_types=1);

use App\Domain\Attendance\Events\StudentMarkedAbsent;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Notifications\Contracts\SmsProviderInterface;
use App\Domain\Notifications\Jobs\SendSmsJob;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\ValueObjects\SmsSendResult;
use Illuminate\Support\Facades\Queue;

test('une absence crée un SmsMessage en file et déclenche SendSmsJob pour chaque tuteur joignable', function () {
    Queue::fake();

    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    $guardianWithPhone = Guardian::factory()->create(['establishment_id' => $establishment->id, 'phone' => '+2250700000000']);
    $guardianWithoutPhone = Guardian::factory()->create(['establishment_id' => $establishment->id, 'phone' => null]);
    $student->guardians()->attach([
        $guardianWithPhone->id => ['establishment_id' => $establishment->id],
        $guardianWithoutPhone->id => ['establishment_id' => $establishment->id],
    ]);

    $session = AttendanceSession::factory()->create(['establishment_id' => $establishment->id]);
    $record = AttendanceRecord::factory()->create([
        'establishment_id' => $establishment->id,
        'attendance_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'absent',
    ]);

    StudentMarkedAbsent::dispatch($record);

    $smsMessage = SmsMessage::sole();

    expect($smsMessage->guardian_id)->toBe($guardianWithPhone->id)
        ->and($smsMessage->status)->toBe('queued')
        ->and($smsMessage->body_rendered)->toContain($student->first_name);

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job) => $job->smsMessageId === $smsMessage->id);
});

test('SendSmsJob marque le message comme envoyé via le provider configuré', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $guardian = Guardian::factory()->create(['establishment_id' => $establishment->id, 'phone' => '+2250700000000']);

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
