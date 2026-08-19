<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Attendance\Sessions\Index;
use Livewire\Livewire;

test('une session sans présences saisies affiche le statut à faire, une session complétée affiche fait', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'status' => 'active',
    ]);

    $pendingSession = AttendanceSession::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
    ]);
    $doneSession = AttendanceSession::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
    ]);
    AttendanceRecord::create([
        'establishment_id' => $establishment->id,
        'attendance_session_id' => $doneSession->id,
        'student_id' => $student->id,
        'status' => 'absent',
    ]);

    $component = Livewire::test(Index::class);

    $sessions = $component->viewData('sessions')->keyBy('id');

    expect($sessions[$pendingSession->id]->records_count)->toBe(0)
        ->and($sessions[$doneSession->id]->records_count)->toBe(1)
        ->and($sessions[$doneSession->id]->absences_count)->toBe(1);

    $component->assertSee('À faire')->assertSee('Fait');
});

test('une session à faire dont la date est passée affiche le statut en retard', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);

    AttendanceSession::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'session_date' => now()->subWeek()->toDateString(),
    ]);

    Livewire::test(Index::class)
        ->assertSee('En retard')
        ->assertDontSee('À faire');
});

test('une bannière signale le nombre d’appels à faire aujourd’hui', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);

    AttendanceSession::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'session_date' => now()->toDateString(),
    ]);
    AttendanceSession::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'session_date' => now()->toDateString(),
    ]);

    Livewire::test(Index::class)
        ->assertSee('2 appels à faire aujourd\'hui');
});

test('une bannière positive confirme que tout est fait quand les appels du jour sont complétés', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    $session = AttendanceSession::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'session_date' => now()->toDateString(),
    ]);
    AttendanceRecord::create([
        'establishment_id' => $establishment->id,
        'attendance_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'present',
    ]);

    Livewire::test(Index::class)
        ->assertSee('Tout est fait pour aujourd\'hui', false)
        ->assertDontSee('à faire aujourd\'hui', false);
});
