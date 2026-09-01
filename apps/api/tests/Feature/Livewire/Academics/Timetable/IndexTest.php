<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
use App\Livewire\Academics\Timetable\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->admin = createLocalAdmin($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);

    $this->schoolYear = SchoolYear::factory()->create();
    $this->classroom = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    $this->slot = TimetableSlot::create([
        'establishment_id' => $this->establishment->id,
        'label' => '1',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'sequence' => 1,
    ]);

    $this->teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->subject = Subject::factory()->create();
    $this->assignment = TeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $this->teacher->id,
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
});

test('créer une séance avec un couple enseignant/matière affecté fonctionne', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class, ['classroom' => $this->classroom])
        ->call('openCell', 'monday', $this->slot->id)
        ->set('teacher_assignment_id', $this->assignment->id)
        ->call('save')
        ->assertHasNoErrors();

    $session = TimetableSession::sole();
    expect($session->user_id)->toBe($this->teacher->id)
        ->and($session->subject_id)->toBe($this->subject->id)
        ->and($session->day_of_week->value)->toBe('monday');
});

test('un enseignant non affecté à cette classe/matière est rejeté', function () {
    $this->actingAs($this->admin);
    $otherTeacher = createUserWithRole($this->establishment, 'enseignant');
    $otherSubject = Subject::factory()->create();
    $foreignAssignment = TeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $otherTeacher->id,
        'classroom_id' => Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id])->id,
        'subject_id' => $otherSubject->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    Livewire::test(Index::class, ['classroom' => $this->classroom])
        ->call('openCell', 'monday', $this->slot->id)
        ->set('teacher_assignment_id', $foreignAssignment->id)
        ->call('save')
        ->assertHasErrors(['teacher_assignment_id']);

    expect(TimetableSession::count())->toBe(0);
});

test('la classe ne peut pas avoir deux séances sur le même créneau', function () {
    $this->actingAs($this->admin);

    TimetableSession::create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->teacher->id,
        'day_of_week' => 'monday',
        'timetable_slot_id' => $this->slot->id,
    ]);

    $otherTeacher = createUserWithRole($this->establishment, 'enseignant');
    $otherSubject = Subject::factory()->create();
    $otherAssignment = TeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $otherTeacher->id,
        'classroom_id' => $this->classroom->id,
        'subject_id' => $otherSubject->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    Livewire::test(Index::class, ['classroom' => $this->classroom])
        ->set('day_of_week', 'monday')
        ->set('timetable_slot_id', $this->slot->id)
        ->set('teacher_assignment_id', $otherAssignment->id)
        ->call('save')
        ->assertHasErrors(['conflict']);

    expect(TimetableSession::count())->toBe(1);
});

test('un enseignant déjà occupé sur ce créneau ne peut pas être planifié ailleurs', function () {
    $this->actingAs($this->admin);

    $otherClassroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    TeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $this->teacher->id,
        'classroom_id' => $otherClassroom->id,
        'subject_id' => $this->subject->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    TimetableSession::create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $otherClassroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->teacher->id,
        'day_of_week' => 'monday',
        'timetable_slot_id' => $this->slot->id,
    ]);

    Livewire::test(Index::class, ['classroom' => $this->classroom])
        ->set('day_of_week', 'monday')
        ->set('timetable_slot_id', $this->slot->id)
        ->set('teacher_assignment_id', $this->assignment->id)
        ->call('save')
        ->assertHasErrors(['conflict']);

    expect(TimetableSession::count())->toBe(1);
});

test('une salle déjà occupée sur ce créneau ne peut pas être réutilisée', function () {
    $this->actingAs($this->admin);

    $otherClassroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $otherTeacher = createUserWithRole($this->establishment, 'enseignant');
    $otherAssignmentSameRoom = TeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $otherTeacher->id,
        'classroom_id' => $otherClassroom->id,
        'subject_id' => $this->subject->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    TimetableSession::create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $otherClassroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $otherTeacher->id,
        'day_of_week' => 'monday',
        'timetable_slot_id' => $this->slot->id,
        'room' => 'Salle 12',
    ]);
    expect($otherAssignmentSameRoom)->not->toBeNull();

    Livewire::test(Index::class, ['classroom' => $this->classroom])
        ->set('day_of_week', 'monday')
        ->set('timetable_slot_id', $this->slot->id)
        ->set('teacher_assignment_id', $this->assignment->id)
        ->set('room', 'Salle 12')
        ->call('save')
        ->assertHasErrors(['conflict']);

    expect(TimetableSession::count())->toBe(1);
});

test('modifier une séance existante ne se bloque pas elle-même', function () {
    $this->actingAs($this->admin);

    $session = TimetableSession::create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->teacher->id,
        'day_of_week' => 'monday',
        'timetable_slot_id' => $this->slot->id,
    ]);

    Livewire::test(Index::class, ['classroom' => $this->classroom])
        ->call('openCell', 'monday', $this->slot->id)
        ->set('room', 'Salle 3')
        ->call('save')
        ->assertHasNoErrors();

    expect($session->fresh()->room)->toBe('Salle 3')
        ->and(TimetableSession::count())->toBe(1);
});

test('une classe de cycle préscolaire/primaire renvoie 404', function () {
    $this->actingAs($this->admin);

    $primaryClassroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    $this->get(route('academics.timetable.index', $primaryClassroom))->assertNotFound();
});

test('un enseignant sans le pouvoir timetable.manage voit la grille en lecture seule', function () {
    $this->actingAs($this->teacher);

    Livewire::test(Index::class, ['classroom' => $this->classroom])
        ->assertSet('canManage', false);
});

test('la grille est cloisonnée par établissement', function () {
    $otherEstablishment = Establishment::factory()->create();
    $otherAdmin = createLocalAdmin($otherEstablishment, 'directeur');
    actingInEstablishment($otherEstablishment);
    $this->actingAs($otherAdmin);

    $this->get(route('academics.timetable.index', $this->classroom))->assertNotFound();
});
