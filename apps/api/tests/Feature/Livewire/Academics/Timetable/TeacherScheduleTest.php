<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
use App\Livewire\Academics\Timetable\TeacherSchedule;
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
});

test('affiche les séances du bon enseignant, pas celles d’un collègue', function () {
    $this->actingAs($this->admin);

    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $colleague = createUserWithRole($this->establishment, 'enseignant');
    $mySubject = Subject::factory()->create(['name' => 'Mathématiques']);
    $colleagueSubject = Subject::factory()->create(['name' => 'Histoire-Géographie']);

    TimetableSession::create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $this->classroom->id,
        'subject_id' => $mySubject->id,
        'user_id' => $teacher->id,
        'day_of_week' => 'monday',
        'timetable_slot_id' => $this->slot->id,
    ]);
    TimetableSession::create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $this->classroom->id,
        'subject_id' => $colleagueSubject->id,
        'user_id' => $colleague->id,
        'day_of_week' => 'tuesday',
        'timetable_slot_id' => $this->slot->id,
    ]);

    Livewire::test(TeacherSchedule::class, ['user' => $teacher])
        ->assertSee('Mathématiques')
        ->assertDontSee('Histoire-Géographie');
});

test('404 si l’utilisateur ciblé n’est pas un enseignant actif de l’établissement courant', function () {
    $this->actingAs($this->admin);

    $cashier = createUserWithRole($this->establishment, 'caissier');

    $this->get(route('academics.timetable.teachers.show', $cashier))->assertNotFound();
});

test('403 si l’utilisateur connecté est lui-même enseignant, même en visant son propre id', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(TeacherSchedule::class, ['user' => $teacher])->assertForbidden();
});

test('cloisonnement multi-établissement : un enseignant d’un autre établissement renvoie 404', function () {
    $otherEstablishment = Establishment::factory()->create();
    $otherTeacher = createUserWithRole($otherEstablishment, 'enseignant');

    $this->actingAs($this->admin);

    $this->get(route('academics.timetable.teachers.show', $otherTeacher))->assertNotFound();
});
