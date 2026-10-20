<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Models\ArabicTimetableSession;
use App\Domain\Timetable\Models\ArabicTimetableSlot;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
use App\Livewire\Academics\Timetable\Index as FrenchTimetableIndex;
use App\Livewire\Arabic\Timetable\Browse;
use App\Livewire\Arabic\Timetable\Index;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * Emploi du temps des classes arabes et conflits avec le français — voir
 * docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
 */

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true, 'arabic_timetable_days' => ['monday', 'saturday']]);
    $this->admin = createLocalAdmin($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->arabicClassroom = ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);

    // Créneau arabe 10:00–11:00, à cheval sur le créneau français 09:30–10:30.
    $this->arabicSlot = ArabicTimetableSlot::create(['establishment_id' => $this->establishment->id, 'label' => 'ح1', 'start_time' => '10:00', 'end_time' => '11:00', 'sequence' => 1]);
    $this->frenchSlot = TimetableSlot::create(['establishment_id' => $this->establishment->id, 'label' => 'M2', 'start_time' => '09:30', 'end_time' => '10:30', 'sequence' => 1]);

    $this->teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->arabicSubject = ArabicSubject::factory()->create(['name' => 'القرآن الكريم']);
    $this->assignment = ArabicTeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $this->teacher->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
        'arabic_subject_id' => $this->arabicSubject->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    $this->classroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
});

function placeFrenchSession(object $test, array $attributes = []): TimetableSession
{
    return TimetableSession::create([
        'establishment_id' => $test->establishment->id,
        'school_year_id' => $test->schoolYear->id,
        'classroom_id' => $test->classroom->id,
        'subject_id' => Subject::factory()->create()->id,
        'user_id' => createUserWithRole($test->establishment, 'enseignant')->id,
        'day_of_week' => 'monday',
        'timetable_slot_id' => $test->frenchSlot->id,
        ...$attributes,
    ]);
}

function saveArabicSession(object $test, string $day = 'monday', string $room = ''): Testable
{
    return Livewire::test(Index::class, ['arabicClassroom' => $test->arabicClassroom])
        ->call('openCell', $day, $test->arabicSlot->id)
        ->set('teacher_assignment_id', $test->assignment->id)
        ->set('room', $room)
        ->call('save');
}

test('une séance arabe se place sur un jour arabe, samedi compris', function () {
    saveArabicSession($this, 'saturday', 'B2')->assertHasNoErrors();

    $session = ArabicTimetableSession::sole();

    expect($session->day_of_week->value)->toBe('saturday')
        ->and($session->user_id)->toBe($this->teacher->id)
        ->and($session->arabic_subject_id)->toBe($this->arabicSubject->id)
        ->and($session->room)->toBe('B2')
        ->and($session->school_year_id)->toBe($this->schoolYear->id);
});

test('la grille affiche les jours arabes de l’école et le nom de la matière', function () {
    saveArabicSession($this)->assertHasNoErrors();

    Livewire::test(Index::class, ['arabicClassroom' => $this->arabicClassroom])
        ->assertSee(__('Samedi'))
        ->assertDontSee(__('Mardi'))
        ->assertSee('القرآن الكريم');
});

test('un jour qui n’est pas un jour arabe est refusé', function () {
    saveArabicSession($this, 'tuesday')->assertHasErrors(['conflict']);

    expect(ArabicTimetableSession::count())->toBe(0);
});

test('l’enseignant doit être affecté à la classe arabe pour la matière', function () {
    $otherAssignment = ArabicTeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $this->teacher->id,
        'arabic_classroom_id' => ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id])->id,
        'arabic_subject_id' => $this->arabicSubject->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    Livewire::test(Index::class, ['arabicClassroom' => $this->arabicClassroom])
        ->call('openCell', 'monday', $this->arabicSlot->id)
        ->set('teacher_assignment_id', $otherAssignment->id)
        ->call('save')
        ->assertHasErrors(['teacher_assignment_id']);
});

test('la classe arabe ne peut pas avoir deux cours sur le même créneau', function () {
    saveArabicSession($this)->assertHasNoErrors();

    $otherTeacher = createUserWithRole($this->establishment, 'enseignant');
    $otherAssignment = ArabicTeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $otherTeacher->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
        'arabic_subject_id' => ArabicSubject::factory()->create()->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    // Ouvrir la case existante passe en modification ; on vise une
    // création concurrente en forçant editingId à vide.
    Livewire::test(Index::class, ['arabicClassroom' => $this->arabicClassroom])
        ->call('openCell', 'monday', $this->arabicSlot->id)
        ->set('editingId', null)
        ->set('teacher_assignment_id', $otherAssignment->id)
        ->call('save')
        ->assertHasErrors(['conflict']);

    expect(ArabicTimetableSession::count())->toBe(1);
});

test('l’enseignant ne peut pas avoir un cours français qui chevauche', function () {
    placeFrenchSession($this, ['user_id' => $this->teacher->id]);

    saveArabicSession($this)
        ->assertHasErrors(['conflict'])
        ->assertSee(__('Cet enseignant a déjà un cours en français sur cette plage (:classroom).', ['classroom' => $this->classroom->name]));
});

test('la salle ne peut pas être occupée par un cours français qui chevauche', function () {
    placeFrenchSession($this, ['room' => 'B2']);

    saveArabicSession($this, 'monday', 'B2')->assertHasErrors(['conflict']);
    saveArabicSession($this, 'monday', 'B3')->assertHasNoErrors();
});

test('des élèves communs avec une classe française en cours au même moment bloquent la séance', function () {
    placeFrenchSession($this);

    foreach ([1, 2] as $i) {
        Enrollment::factory()->create([
            'establishment_id' => $this->establishment->id,
            'school_year_id' => $this->schoolYear->id,
            'classroom_id' => $this->classroom->id,
            'arabic_classroom_id' => $this->arabicClassroom->id,
        ]);
    }

    saveArabicSession($this)
        ->assertHasErrors(['conflict'])
        ->assertSee(trans_choice(
            '{1} 1 élève de cette classe a cours en français sur cette plage (:classroom).|[2,*] :count élèves de cette classe ont cours en français sur cette plage (:classroom).',
            2,
            ['count' => 2, 'classroom' => $this->classroom->name],
        ));
});

test('sans élève commun ni chevauchement, la séance est acceptée', function () {
    placeFrenchSession($this);
    Enrollment::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id, 'classroom_id' => $this->classroom->id]);

    saveArabicSession($this)->assertHasNoErrors();

    // Créneau français qui se termine quand le créneau arabe commence.
    $this->frenchSlot->update(['start_time' => '09:00', 'end_time' => '10:00']);
    ArabicTimetableSession::query()->delete();
    Enrollment::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id, 'classroom_id' => $this->classroom->id, 'arabic_classroom_id' => $this->arabicClassroom->id]);

    saveArabicSession($this)->assertHasNoErrors();
});

test('une séance française est refusée quand l’enseignant, la salle ou des élèves ont un cours arabe qui chevauche', function () {
    saveArabicSession($this, 'monday', 'B2')->assertHasNoErrors();

    $frenchSubject = Subject::factory()->create();
    $frenchAssignment = fn ($userId) => TeacherAssignment::create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $userId,
        'classroom_id' => $this->classroom->id,
        'subject_id' => $frenchSubject->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    $saveFrench = fn (TeacherAssignment $assignment, string $room = '') => Livewire::test(FrenchTimetableIndex::class, ['classroom' => $this->classroom])
        ->call('openCell', 'monday', $this->frenchSlot->id)
        ->set('teacher_assignment_id', $assignment->id)
        ->set('room', $room)
        ->call('save');

    // Même enseignant.
    $saveFrench($frenchAssignment($this->teacher->id))->assertHasErrors(['conflict']);

    // Même salle.
    $otherTeacherAssignment = $frenchAssignment(createUserWithRole($this->establishment, 'enseignant')->id);
    $saveFrench($otherTeacherAssignment, 'B2')->assertHasErrors(['conflict']);

    // Élève commun.
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => $this->classroom->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
    ]);
    $saveFrench($otherTeacherAssignment, 'C1')->assertHasErrors(['conflict']);

    $enrollment->update(['status' => 'withdrawn']);
    $saveFrench($otherTeacherAssignment, 'C1')->assertHasNoErrors();

    expect(TimetableSession::count())->toBe(1);
});

test('une séance peut être modifiée puis retirée', function () {
    saveArabicSession($this, 'monday', 'B2')->assertHasNoErrors();

    Livewire::test(Index::class, ['arabicClassroom' => $this->arabicClassroom])
        ->call('openCell', 'monday', $this->arabicSlot->id)
        ->assertSet('teacher_assignment_id', $this->assignment->id)
        ->assertSet('room', 'B2')
        ->set('room', 'B4')
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicTimetableSession::sole()->room)->toBe('B4');

    Livewire::test(Index::class, ['arabicClassroom' => $this->arabicClassroom])
        ->call('openCell', 'monday', $this->arabicSlot->id)
        ->call('delete');

    expect(ArabicTimetableSession::count())->toBe(0);
});

test('un enseignant consulte la grille sans pouvoir la modifier', function () {
    $this->actingAs($this->teacher);

    Livewire::test(Index::class, ['arabicClassroom' => $this->arabicClassroom])
        ->assertOk()
        ->assertSet('canManage', false)
        ->call('openCell', 'monday', $this->arabicSlot->id)
        ->assertSet('showForm', false);
});

test('la liste des classes arabes est réservée au personnel administratif', function () {
    Livewire::test(Browse::class)->assertOk()->assertSee($this->arabicClassroom->name);

    $this->actingAs($this->teacher);
    Livewire::test(Browse::class)->assertForbidden();
});

test('hors école franco-arabe, l’emploi du temps arabe est inaccessible', function () {
    $this->establishment->update(['is_arabe' => false]);

    Livewire::test(Index::class, ['arabicClassroom' => $this->arabicClassroom])->assertForbidden();
    Livewire::test(Browse::class)->assertForbidden();
});

test('la classe arabe d’une autre école est introuvable', function () {
    $other = Establishment::factory()->create(['is_arabe' => true]);
    $directeur = createLocalAdmin($other, 'directeur');
    actingInEstablishment($other);

    $this->actingAs($directeur)
        ->get(route('arabic.timetable.index', $this->arabicClassroom))
        ->assertNotFound();
});
