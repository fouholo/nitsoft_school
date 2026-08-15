<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use App\Livewire\Grading\GradeSheets\Primaire\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    // educateur : seul rôle admin-tier habilité à saisir des notes depuis le
    // chantier "privilèges par rôle" (RolePermissions::MATRIX['grades.enter']).
    $this->admin = createUserWithRole($this->establishment, 'educateur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->primaireClassroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);
    $this->subject = Subject::factory()->create(['is_prescolaire_primaire' => true]);
});

test('la création d’une feuille de notes pour une classe primaire fonctionne', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->set('subject_id', $this->subject->id)
        ->set('composition_number', 1)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(GradeSheet::count())->toBe(1);

    $gradeSheet = GradeSheet::sole();

    expect($gradeSheet->type)->toBe('composition')
        ->and($gradeSheet->term_id)->toBeNull()
        ->and($gradeSheet->composition_number)->toBe(1);
});

test('le n° de composition est requis', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->set('subject_id', $this->subject->id)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['composition_number']);

    expect(GradeSheet::count())->toBe(0);
});

test('sélectionner une classe pré-remplit le poids par défaut à 1', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->assertSet('weight', 1.0);
});

test('la liste des matières est filtrée sur les matières préscolaire/primaire', function () {
    $secondaireOnly = Subject::factory()->create(['is_prescolaire_primaire' => false, 'is_secondaire' => true]);

    $subjects = Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->viewData('subjects');

    expect($subjects->pluck('id'))->toContain($this->subject->id)
        ->not->toContain($secondaireOnly->id);
});

test('une matière incompatible avec le cycle primaire est rejetée côté serveur', function () {
    $secondaireOnly = Subject::factory()->create(['is_prescolaire_primaire' => false, 'is_secondaire' => true]);

    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->set('subject_id', $secondaireOnly->id)
        ->set('composition_number', 1)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['subject_id']);

    expect(GradeSheet::count())->toBe(0);
});

test('un enseignant avec une affectation classe entière voit toutes les matières compatibles et peut créer une évaluation', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    TeacherAssignment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $teacher->id,
        'classroom_id' => $this->primaireClassroom->id,
        'subject_id' => null,
    ]);
    $otherPrimaireSubject = Subject::factory()->create(['is_prescolaire_primaire' => true]);

    $this->actingAs($teacher);

    $subjects = Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->viewData('subjects');

    expect($subjects->pluck('id'))
        ->toContain($this->subject->id)
        ->toContain($otherPrimaireSubject->id);

    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->set('subject_id', $otherPrimaireSubject->id)
        ->set('composition_number', 1)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(GradeSheet::count())->toBe(1);
});

test('un enseignant sans affectation sur la classe est refusé', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->set('subject_id', $this->subject->id)
        ->set('composition_number', 1)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertForbidden();

    expect(GradeSheet::count())->toBe(0);
});
