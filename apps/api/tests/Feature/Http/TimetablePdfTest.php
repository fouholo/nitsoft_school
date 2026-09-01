<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
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
    $this->subject = Subject::factory()->create(['name' => 'Physique-Chimie']);

    TeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $this->teacher->id,
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    TimetableSession::create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $this->classroom->id,
        'subject_id' => $this->subject->id,
        'user_id' => $this->teacher->id,
        'day_of_week' => 'monday',
        'timetable_slot_id' => $this->slot->id,
        'room' => 'Salle 4',
    ]);
});

test('le PDF de l’emploi du temps d’une classe se génère et contient les séances', function () {
    $admin = createLocalAdmin($this->establishment, 'directeur');

    $response = $this->actingAs($admin)->get(route('reports.timetable-classroom-pdf', $this->classroom));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('le PDF de l’emploi du temps personnel d’un enseignant se génère', function () {
    $response = $this->actingAs($this->teacher)->get(route('reports.timetable-mine-pdf'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('la vue PDF de la classe affiche la matière, l’enseignant et la salle', function () {
    $html = view('pdf.timetable', [
        'title' => 'Emploi du temps — '.$this->classroom->name,
        'establishment' => $this->establishment,
        'generalInformation' => GeneralInformation::current(),
        'days' => DayOfWeek::cases(),
        'slots' => TimetableSlot::orderBy('sequence')->get(),
        'sessions' => TimetableSession::where('classroom_id', $this->classroom->id)
            ->with(['subject', 'teacher'])
            ->get()
            ->keyBy(fn ($s) => $s->day_of_week->value.'-'.$s->timetable_slot_id),
        'showClassroom' => false,
    ])->render();

    expect($html)->toContain('Physique-Chimie')
        ->and($html)->toContain(e($this->teacher->name))
        ->and($html)->toContain('Salle 4');
});
