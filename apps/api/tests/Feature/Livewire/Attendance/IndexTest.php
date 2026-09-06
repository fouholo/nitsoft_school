<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
use App\Livewire\Attendance\Sessions\Index;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Crée une TimetableSession secondaire (+ son créneau + l'affectation
 * enseignant requise) pour un jour de semaine donné — factorisé ici car
 * réutilisé par la quasi-totalité des nouveaux tests "séances du jour".
 */
function createTimetableSession(
    Establishment $establishment,
    SchoolYear $schoolYear,
    Classroom $classroom,
    Subject $subject,
    User $teacher,
    string $dayOfWeek,
    int $sequence = 1,
): TimetableSession {
    $slot = TimetableSlot::create([
        'establishment_id' => $establishment->id,
        'label' => "Créneau {$sequence}",
        'start_time' => sprintf('%02d:00', 7 + $sequence),
        'end_time' => sprintf('%02d:00', 8 + $sequence),
        'sequence' => $sequence,
    ]);

    TeacherAssignment::firstOrCreate([
        'establishment_id' => $establishment->id,
        'user_id' => $teacher->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'school_year_id' => $schoolYear->id,
    ]);

    return TimetableSession::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'user_id' => $teacher->id,
        'day_of_week' => $dayOfWeek,
        'timetable_slot_id' => $slot->id,
    ]);
}

test('une session sans présences saisies affiche le statut à faire, une session complétée affiche fait', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create();
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
        ->assertSee('2 appel(s) à faire aujourd\'hui');
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
        ->assertSee('Tout est fait pour aujourd\'hui')
        ->assertDontSee('à faire aujourd\'hui', false);
});

test('un enseignant secondaire ne voit que ses propres séances du jour', function () {
    Carbon::setTestNow('2020-01-06 08:00:00'); // lundi

    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $subject = Subject::factory()->create();

    $teacher = createUserWithRole($establishment, 'enseignant');
    $otherTeacher = createUserWithRole($establishment, 'enseignant');

    $mySession = createTimetableSession($establishment, $schoolYear, $classroom, $subject, $teacher, 'monday', 1);
    createTimetableSession($establishment, $schoolYear, $classroom, $subject, $otherTeacher, 'monday', 2);

    test()->actingAs($teacher);

    $sessions = Livewire::test(Index::class)->viewData('todaysTimetableSessions');

    expect($sessions->pluck('id')->all())->toBe([$mySession->id]);

    Carbon::setTestNow();
});

test('un admin voit toutes les séances du jour avec le nom de l’enseignant', function () {
    Carbon::setTestNow('2020-01-06 08:00:00'); // lundi

    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $subject = Subject::factory()->create();

    $teacherA = createUserWithRole($establishment, 'enseignant');
    $teacherB = createUserWithRole($establishment, 'enseignant');
    createTimetableSession($establishment, $schoolYear, $classroom, $subject, $teacherA, 'monday', 1);
    createTimetableSession($establishment, $schoolYear, $classroom, $subject, $teacherB, 'monday', 2);

    test()->actingAs($admin);

    Livewire::test(Index::class)
        ->assertSee('Séances du jour')
        ->assertSee($teacherA->name)
        ->assertSee($teacherB->name);

    Carbon::setTestNow();
});

test('démarrer l’appel depuis une séance planifiée crée un appel pré-rempli', function () {
    Carbon::setTestNow('2020-01-06 08:00:00'); // lundi

    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $subject = Subject::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    $timetableSession = createTimetableSession($establishment, $schoolYear, $classroom, $subject, $teacher, 'monday', 1);

    test()->actingAs($teacher);

    Livewire::test(Index::class)
        ->call('startFromTimetable', $timetableSession->id)
        ->assertRedirect();

    $attendanceSession = AttendanceSession::sole();
    expect($attendanceSession->timetable_session_id)->toBe($timetableSession->id)
        ->and($attendanceSession->classroom_id)->toBe($classroom->id)
        ->and($attendanceSession->subject_id)->toBe($subject->id)
        ->and($attendanceSession->teacher_id)->toBe($teacher->id)
        ->and($attendanceSession->session_date->toDateString())->toBe('2020-01-06')
        ->and($attendanceSession->started_at)->toBe('08:00');

    Carbon::setTestNow();
});

test('recliquer sur la même séance le même jour ne crée pas de doublon', function () {
    Carbon::setTestNow('2020-01-06 08:00:00'); // lundi

    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $subject = Subject::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    $timetableSession = createTimetableSession($establishment, $schoolYear, $classroom, $subject, $teacher, 'monday', 1);

    test()->actingAs($teacher);

    Livewire::test(Index::class)->call('startFromTimetable', $timetableSession->id);
    Livewire::test(Index::class)->call('startFromTimetable', $timetableSession->id);

    expect(AttendanceSession::count())->toBe(1);

    Carbon::setTestNow();
});

test('un enseignant ne peut pas démarrer l’appel sur la séance d’un collègue', function () {
    Carbon::setTestNow('2020-01-06 08:00:00'); // lundi

    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $subject = Subject::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    $otherTeacher = createUserWithRole($establishment, 'enseignant');

    $timetableSession = createTimetableSession($establishment, $schoolYear, $classroom, $subject, $teacher, 'monday', 1);

    test()->actingAs($otherTeacher);

    Livewire::test(Index::class)
        ->call('startFromTimetable', $timetableSession->id)
        ->assertForbidden();

    expect(AttendanceSession::count())->toBe(0);

    Carbon::setTestNow();
});

test('un admin peut démarrer l’appel sur la séance de n’importe quel enseignant', function () {
    Carbon::setTestNow('2020-01-06 08:00:00'); // lundi

    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $subject = Subject::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    $timetableSession = createTimetableSession($establishment, $schoolYear, $classroom, $subject, $teacher, 'monday', 1);

    test()->actingAs($admin);

    Livewire::test(Index::class)
        ->call('startFromTimetable', $timetableSession->id)
        ->assertRedirect();

    expect(AttendanceSession::sole()->teacher_id)->toBe($teacher->id);

    Carbon::setTestNow();
});

test('une classe préscolaire/primaire n’apparaît jamais dans les séances du jour', function () {
    Carbon::setTestNow('2020-01-06 08:00:00'); // lundi

    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $schoolYear = SchoolYear::factory()->create();
    $primaryClassroom = Classroom::factory()->primaire()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $subject = Subject::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    // Pas de TimetableSession possible pour le préscolaire/primaire — on
    // vérifie simplement que la liste reste vide en l'absence de planning.
    TeacherAssignment::create([
        'establishment_id' => $establishment->id,
        'user_id' => $teacher->id,
        'classroom_id' => $primaryClassroom->id,
        'subject_id' => $subject->id,
        'school_year_id' => $schoolYear->id,
    ]);

    test()->actingAs($teacher);

    $sessions = Livewire::test(Index::class)->viewData('todaysTimetableSessions');

    expect($sessions)->toBeEmpty();

    Carbon::setTestNow();
});

test('un week-end, la liste des séances du jour est vide sans erreur', function () {
    Carbon::setTestNow('2020-01-04 08:00:00'); // samedi

    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);

    test()->actingAs($teacher);

    $sessions = Livewire::test(Index::class)->viewData('todaysTimetableSessions');

    expect($sessions)->toBeEmpty();

    Carbon::setTestNow();
});

test('la contrainte unique empêche un doublon (timetable_session_id, session_date) mais autorise plusieurs appels libres le même jour', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $subject = Subject::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    $timetableSession = createTimetableSession($establishment, $schoolYear, $classroom, $subject, $teacher, 'monday', 1);

    AttendanceSession::create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'timetable_session_id' => $timetableSession->id,
        'teacher_id' => $teacher->id,
        'session_date' => '2020-01-06',
    ]);

    expect(fn () => AttendanceSession::create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'timetable_session_id' => $timetableSession->id,
        'teacher_id' => $teacher->id,
        'session_date' => '2020-01-06',
    ]))->toThrow(QueryException::class);

    // deux appels libres (timetable_session_id null) le même jour : autorisé.
    AttendanceSession::create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'teacher_id' => $teacher->id,
        'session_date' => '2020-01-06',
    ]);
    AttendanceSession::create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'teacher_id' => $teacher->id,
        'session_date' => '2020-01-06',
    ]);

    expect(AttendanceSession::whereNull('timetable_session_id')->count())->toBe(2);
});
