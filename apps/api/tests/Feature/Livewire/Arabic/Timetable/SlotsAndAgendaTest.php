<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\ArabicTimetableSession;
use App\Domain\Timetable\Models\ArabicTimetableSlot;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
use App\Domain\Timetable\Services\CrossTrackConflicts;
use App\Domain\Timetable\Services\WeeklyAgenda;
use App\Livewire\Academics\Timetable\Index as FrenchTimetableIndex;
use App\Livewire\Academics\Timetable\MySchedule;
use App\Livewire\Arabic\Timetable\SlotsIndex;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    $this->admin = createLocalAdmin($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);
    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
});

test('le chevauchement compare les horaires réels, deux plages qui se touchent ne se chevauchent pas', function () {
    expect(CrossTrackConflicts::overlaps('08:00', '09:00', '08:30', '09:30'))->toBeTrue()
        ->and(CrossTrackConflicts::overlaps('08:00:00', '12:00:00', '09:00', '10:00'))->toBeTrue()
        ->and(CrossTrackConflicts::overlaps('08:00', '09:00', '09:00', '10:00'))->toBeFalse()
        ->and(CrossTrackConflicts::overlaps('10:00', '11:00', '08:00', '09:00'))->toBeFalse();
});

test('les jours arabes valent lundi à vendredi par défaut, puis ceux choisis par l’école, dans l’ordre de la semaine', function () {
    expect($this->establishment->arabicTimetableDays())->toBe(DayOfWeek::weekdays());

    $this->establishment->update(['arabic_timetable_days' => ['sunday', 'wednesday', 'inconnu']]);

    expect($this->establishment->refresh()->arabicTimetableDays())->toBe([DayOfWeek::Wednesday, DayOfWeek::Sunday]);
});

test('un directeur crée un créneau arabe et choisit les jours de cours arabes', function () {
    Livewire::test(SlotsIndex::class)
        ->call('create')
        ->assertSet('sequence', 1)
        ->set('label', 'Première heure')
        ->set('start_time', '15:00')
        ->set('end_time', '16:00')
        ->call('save')
        ->assertHasNoErrors()
        ->set('days', ['wednesday', 'saturday'])
        ->call('saveDays')
        ->assertHasNoErrors()
        ->assertSet('daysJustSaved', true);

    expect(ArabicTimetableSlot::sole()->label)->toBe('Première heure')
        ->and($this->establishment->refresh()->arabicTimetableDays())->toBe([DayOfWeek::Wednesday, DayOfWeek::Saturday]);
});

test('il faut au moins un jour de cours arabe, et deux créneaux ne partagent pas le même ordre', function () {
    ArabicTimetableSlot::create(['establishment_id' => $this->establishment->id, 'label' => 'A', 'start_time' => '08:00', 'end_time' => '09:00', 'sequence' => 1]);

    Livewire::test(SlotsIndex::class)
        ->set('days', [])
        ->call('saveDays')
        ->assertHasErrors(['days'])
        ->call('create')
        ->set('label', 'B')
        ->set('start_time', '09:00')
        ->set('end_time', '10:00')
        ->set('sequence', 1)
        ->call('save')
        ->assertHasErrors(['sequence'])
        ->set('end_time', '08:30')
        ->call('save')
        ->assertHasErrors(['end_time']);
});

test('sans timetable.manage, on consulte la grille arabe sans la modifier', function () {
    $this->actingAs(createUserWithRole($this->establishment, 'caissier'));

    Livewire::test(SlotsIndex::class)
        ->assertOk()
        ->call('create')
        ->assertForbidden();
});

test('hors école franco-arabe, la grille arabe est inaccessible', function () {
    $this->establishment->update(['is_arabe' => false]);

    Livewire::test(SlotsIndex::class)->assertForbidden();
});

test('l’agenda de l’enseignant réunit séances françaises et arabes, triées par heure, avec les jours arabes', function () {
    $this->establishment->update(['arabic_timetable_days' => ['monday', 'saturday']]);
    $teacher = createUserWithRole($this->establishment, 'enseignant');

    $frenchSlot = TimetableSlot::create(['establishment_id' => $this->establishment->id, 'label' => 'M1', 'start_time' => '08:00', 'end_time' => '09:00', 'sequence' => 1]);
    $arabicSlot = ArabicTimetableSlot::create(['establishment_id' => $this->establishment->id, 'label' => 'A1', 'start_time' => '07:00', 'end_time' => '07:55', 'sequence' => 1]);
    $classroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $arabicClassroom = ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);

    TimetableSession::create([
        'establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id, 'classroom_id' => $classroom->id,
        'subject_id' => Subject::factory()->create(['name' => 'Mathématiques'])->id, 'user_id' => $teacher->id, 'day_of_week' => 'monday', 'timetable_slot_id' => $frenchSlot->id,
    ]);
    foreach (['monday', 'saturday'] as $day) {
        ArabicTimetableSession::create([
            'establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id, 'arabic_classroom_id' => $arabicClassroom->id,
            'arabic_subject_id' => ArabicSubject::factory()->create(['name' => 'الفقه '.$day])->id, 'user_id' => $teacher->id, 'day_of_week' => $day, 'arabic_timetable_slot_id' => $arabicSlot->id,
        ]);
    }

    $agenda = app(WeeklyAgenda::class)->forTeacher($teacher->id, $this->establishment);

    expect($agenda['days'])->toBe([...DayOfWeek::weekdays(), DayOfWeek::Saturday])
        ->and(array_column($agenda['entries']['monday'], 'start'))->toBe(['07:00', '08:00'])
        ->and(array_column($agenda['entries']['monday'], 'arabic'))->toBe([true, false])
        ->and($agenda['entries']['saturday'][0]['classroom'])->toBe($arabicClassroom->name);

    $this->actingAs($teacher);

    Livewire::test(MySchedule::class)
        ->assertSee(__('Samedi'))
        ->assertSee('Mathématiques')
        ->assertSee('الفقه saturday')
        ->assertSee(__('Arabe'));
});

test('hors école franco-arabe, « Mon emploi du temps » garde sa grille par créneau, du lundi au vendredi', function () {
    $this->establishment->update(['is_arabe' => false]);
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    TimetableSlot::create(['establishment_id' => $this->establishment->id, 'label' => 'M1', 'start_time' => '08:00', 'end_time' => '09:00', 'sequence' => 1]);
    $this->actingAs($teacher);

    Livewire::test(MySchedule::class)
        ->assertViewHas('agenda', null)
        ->assertSee(__('Créneau'))
        ->assertSee(__('Vendredi'))
        ->assertDontSee(__('Samedi'));
});

test('la grille d’une classe française reste du lundi au vendredi', function () {
    $classroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);

    Livewire::test(FrenchTimetableIndex::class, ['classroom' => $classroom])
        ->assertViewHas('days', DayOfWeek::weekdays())
        ->assertDontSee(__('Samedi'));
});
