<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Enums\Track;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\Models\SmsTemplate;
use App\Domain\Timetable\Models\ArabicTimetableSession;
use App\Domain\Timetable\Models\ArabicTimetableSlot;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
use App\Livewire\Attendance\Sessions\Index;
use App\Livewire\Attendance\Sessions\Mark;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
 * Appel par classe arabe — voir
 * docs/superpowers/specs/2026-10-30-appel-classes-arabes-design.md.
 */

beforeEach(function () {
    // Un samedi : jour possible en arabe seulement.
    Carbon::setTestNow('2026-10-31 09:00:00');

    $this->establishment = Establishment::factory()->create([
        'is_arabe' => true,
        'type' => EstablishmentType::Secondaire,
        'arabic_timetable_days' => ['monday', 'saturday'],
    ]);
    actingInEstablishment($this->establishment);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($this->directeur);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->classroom = ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $this->subject = ArabicSubject::factory()->create(['name' => 'الفقه']);
    $this->teacher = createUserWithRole($this->establishment, 'enseignant');

    ArabicTeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $this->teacher->id,
        'arabic_classroom_id' => $this->classroom->id,
        'arabic_subject_id' => $this->subject->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    $this->slot = ArabicTimetableSlot::create(['establishment_id' => $this->establishment->id, 'label' => 'ح1', 'start_time' => '15:00', 'end_time' => '16:00', 'sequence' => 1]);
    $this->timetableSession = ArabicTimetableSession::create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_classroom_id' => $this->classroom->id,
        'arabic_subject_id' => $this->subject->id,
        'user_id' => $this->teacher->id,
        'day_of_week' => 'saturday',
        'arabic_timetable_slot_id' => $this->slot->id,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function arabicAttendee(object $test, array $enrollment = []): Student
{
    $student = Student::factory()->create(['establishment_id' => $test->establishment->id]);

    Enrollment::factory()->create([
        'establishment_id' => $test->establishment->id,
        'school_year_id' => $test->schoolYear->id,
        'student_id' => $student->id,
        'arabic_classroom_id' => $test->classroom->id,
        ...$enrollment,
    ]);

    return $student;
}

test('l’enseignant démarre l’appel depuis sa séance arabe, puis le rouvre sans doublon', function () {
    $this->actingAs($this->teacher);

    $component = Livewire::test(Index::class)
        ->assertSee($this->classroom->name)
        ->assertSee(__('Arabe'))
        ->call('startFromArabicTimetable', $this->timetableSession->id);

    $session = AttendanceSession::sole();
    $component->assertRedirect(route('attendance.sessions.mark', $session));

    expect($session->isArabic())->toBeTrue()
        ->and($session->classroom_id)->toBeNull()
        ->and($session->arabic_classroom_id)->toBe($this->classroom->id)
        ->and($session->arabic_subject_id)->toBe($this->subject->id)
        ->and($session->teacher_id)->toBe($this->teacher->id)
        ->and($session->started_at)->toStartWith('15:00')
        ->and($session->classroomName())->toBe($this->classroom->name)
        ->and($session->subjectName())->toBe('الفقه');

    Livewire::test(Index::class)
        ->assertSee(__("Modifier l'appel"))
        ->call('startFromArabicTimetable', $this->timetableSession->id)
        ->assertRedirect(route('attendance.sessions.mark', $session));

    expect(AttendanceSession::count())->toBe(1);
});

test('un autre enseignant ne démarre pas l’appel de cette séance', function () {
    $other = createUserWithRole($this->establishment, 'enseignant');
    ArabicTeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $other->id,
        'arabic_classroom_id' => $this->classroom->id,
        'arabic_subject_id' => ArabicSubject::factory()->create()->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    $this->actingAs($other);

    Livewire::test(Index::class)
        ->assertDontSee($this->classroom->name)
        ->call('startFromArabicTimetable', $this->timetableSession->id)
        ->assertForbidden();
});

test('les séances du jour réunissent les deux filières, triées par heure', function () {
    Carbon::setTestNow('2026-11-02 09:00:00'); // lundi
    $this->timetableSession->update(['day_of_week' => 'monday']);

    $frenchClassroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $frenchSlot = TimetableSlot::create(['establishment_id' => $this->establishment->id, 'label' => 'M1', 'start_time' => '08:00', 'end_time' => '09:00', 'sequence' => 1]);
    $french = TimetableSession::create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $frenchClassroom->id,
        'subject_id' => Subject::factory()->create()->id,
        'user_id' => createUserWithRole($this->establishment, 'enseignant')->id,
        'day_of_week' => 'monday',
        'timetable_slot_id' => $frenchSlot->id,
    ]);

    $entries = Livewire::test(Index::class)->viewData('todayEntries');

    expect($entries->pluck('key')->all())->toBe(['fr-'.$french->id, 'ar-'.$this->timetableSession->id])
        ->and($entries->pluck('arabic')->all())->toBe([false, true]);
});

test('la saisie libre crée un appel arabe, limité aux classes de l’école et aux classes de l’enseignant', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('track', 'arabe')
        ->assertSee(__('Classe arabe'))
        ->set('arabic_classroom_id', $this->classroom->id)
        ->set('arabic_subject_id', $this->subject->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(AttendanceSession::sole()->arabic_classroom_id)->toBe($this->classroom->id);

    Livewire::test(Index::class)
        ->call('create')
        ->set('track', 'arabe')
        ->set('arabic_classroom_id', ArabicClassroom::factory()->create()->id)
        ->call('save')
        ->assertHasErrors(['arabic_classroom_id']);

    Livewire::test(Index::class)
        ->call('create')
        ->set('track', 'arabe')
        ->call('save')
        ->assertHasErrors(['arabic_classroom_id' => 'required']);

    $unassignedClassroom = ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $this->actingAs($this->teacher);

    Livewire::test(Index::class)
        ->call('create')
        ->set('track', 'arabe')
        ->set('arabic_classroom_id', $unassignedClassroom->id)
        ->call('save')
        ->assertForbidden();
});

test('la liste de l’appel réunit les élèves de la classe arabe, principale ou secondaire', function () {
    $secondary = arabicAttendee($this);
    $main = arabicAttendee($this, ['classroom_id' => null, 'main_track' => Track::Arabe]);
    $withdrawn = arabicAttendee($this, ['status' => 'withdrawn']);
    $elsewhere = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create(['establishment_id' => $this->establishment->id, 'student_id' => $elsewhere->id]);

    $session = AttendanceSession::create([
        'establishment_id' => $this->establishment->id,
        'arabic_classroom_id' => $this->classroom->id,
        'arabic_subject_id' => $this->subject->id,
        'teacher_id' => $this->teacher->id,
        'session_date' => now()->toDateString(),
    ]);

    $this->actingAs($this->teacher);

    $ids = Livewire::test(Mark::class, ['session' => $session])
        ->assertOk()
        ->assertSee($this->classroom->name)
        ->viewData('students')
        ->pluck('id');

    expect($ids->all())->toEqualCanonicalizing([$secondary->id, $main->id])
        ->and($ids)->not->toContain($withdrawn->id)
        ->and($ids)->not->toContain($elsewhere->id);
});

test('un enseignant qui n’est pas affecté à la classe arabe ne fait pas l’appel', function () {
    $outsider = createUserWithRole($this->establishment, 'enseignant');
    $session = AttendanceSession::create([
        'establishment_id' => $this->establishment->id,
        'arabic_classroom_id' => $this->classroom->id,
        'teacher_id' => $outsider->id,
        'session_date' => now()->toDateString(),
    ]);
    $this->actingAs($outsider);

    Livewire::test(Mark::class, ['session' => $session])->assertForbidden();
});

test('une absence à un appel arabe envoie un SMS qui nomme le cours d’arabe', function () {
    Queue::fake();
    $student = arabicAttendee($this);
    $guardian = Guardian::factory()->create(['phone' => '0700000000', 'first_name' => 'Awa', 'last_name' => 'Koné']);
    $student->guardians()->attach($guardian->id, ['establishment_id' => $this->establishment->id, 'status' => GuardianLinkStatus::Approved, 'is_primary_contact' => true]);

    $session = AttendanceSession::create([
        'establishment_id' => $this->establishment->id,
        'arabic_classroom_id' => $this->classroom->id,
        'teacher_id' => $this->teacher->id,
        'session_date' => now()->toDateString(),
    ]);

    Livewire::test(Mark::class, ['session' => $session])
        ->set("statuses.{$student->id}", 'absent')
        ->call('save')
        ->assertHasNoErrors();

    $sms = SmsMessage::sole();

    expect(AttendanceRecord::sole()->status)->toBe('absent')
        ->and($sms->template_code)->toBe('attendance_absence_arabic')
        ->and($sms->body_rendered)->toContain("au cours d'arabe")
        ->and($sms->body_rendered)->toContain('Awa Koné');

    // L'école peut personnaliser ce modèle.
    SmsTemplate::create(['establishment_id' => $this->establishment->id, 'code' => 'attendance_absence_arabic', 'body' => 'Absent en arabe : {{student_name}}', 'is_active' => true]);
    SmsMessage::query()->delete();

    Livewire::test(Mark::class, ['session' => $session])
        ->set("statuses.{$student->id}", 'present')
        ->call('save')
        ->set("statuses.{$student->id}", 'absent')
        ->call('save');

    expect(SmsMessage::sole()->body_rendered)->toStartWith('Absent en arabe :');
});

test('une absence à un appel français garde son SMS habituel', function () {
    Queue::fake();
    $classroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $student = arabicAttendee($this, ['classroom_id' => $classroom->id]);
    $guardian = Guardian::factory()->create(['phone' => '0700000000']);
    $student->guardians()->attach($guardian->id, ['establishment_id' => $this->establishment->id, 'status' => GuardianLinkStatus::Approved, 'is_primary_contact' => true]);

    $session = AttendanceSession::create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => $classroom->id,
        'teacher_id' => $this->directeur->id,
        'session_date' => now()->toDateString(),
    ]);

    Livewire::test(Mark::class, ['session' => $session])
        ->set("statuses.{$student->id}", 'absent')
        ->call('save');

    expect(SmsMessage::sole()->template_code)->toBe('attendance_absence')
        ->and(SmsMessage::sole()->body_rendered)->not->toContain("cours d'arabe");
});

test('hors école franco-arabe, ni séance arabe ni champ « Filière », et la saisie reste française', function () {
    $this->establishment->update(['is_arabe' => false]);
    $classroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);

    Livewire::test(Index::class)
        ->assertViewHas('todayEntries', fn ($entries) => $entries->isEmpty())
        ->call('create')
        ->assertDontSee(__('Filière'))
        ->set('track', 'arabe')
        ->set('classroom_id', $classroom->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(AttendanceSession::sole()->classroom_id)->toBe($classroom->id)
        ->and(AttendanceSession::sole()->arabic_classroom_id)->toBeNull();
});
