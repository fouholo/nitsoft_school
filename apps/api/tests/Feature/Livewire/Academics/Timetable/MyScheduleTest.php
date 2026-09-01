<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
use App\Livewire\Academics\Timetable\MySchedule;
use Livewire\Livewire;

test('un enseignant ne voit que ses propres séances', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $slot = TimetableSlot::create([
        'establishment_id' => $establishment->id,
        'label' => '1',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'sequence' => 1,
    ]);

    $me = createUserWithRole($establishment, 'enseignant');
    $colleague = createUserWithRole($establishment, 'enseignant');
    $mySubject = Subject::factory()->create(['name' => 'Mathématiques']);
    $colleagueSubject = Subject::factory()->create(['name' => 'Histoire-Géographie']);

    TimetableSession::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $mySubject->id,
        'user_id' => $me->id,
        'day_of_week' => 'monday',
        'timetable_slot_id' => $slot->id,
    ]);
    TimetableSession::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $colleagueSubject->id,
        'user_id' => $colleague->id,
        'day_of_week' => 'tuesday',
        'timetable_slot_id' => $slot->id,
    ]);

    $this->actingAs($me);

    Livewire::test(MySchedule::class)
        ->assertSee('Mathématiques')
        ->assertDontSee('Histoire-Géographie');
});

test('le personnel administratif peut aussi consulter la vue', function () {
    $establishment = Establishment::factory()->create();
    $admin = createLocalAdmin($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($admin);

    Livewire::test(MySchedule::class)->assertOk();
});
