<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Academics\Models\Term;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use App\Livewire\Grading\GradeSheets\Secondaire\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $this->admin = createUserWithRole($this->establishment, 'educateur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $schoolYear = SchoolYear::factory()->create();
    $this->secondaireClassroom = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);
    $this->subject = Subject::factory()->create(['is_secondaire' => true]);
    $this->term = Term::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $schoolYear->id]);
});

test('la création d’une feuille de notes pour une classe secondaire fonctionne', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->secondaireClassroom->id)
        ->set('subject_id', $this->subject->id)
        ->set('term_id', $this->term->id)
        ->set('title', 'Devoir 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(GradeSheet::count())->toBe(1);

    $gradeSheet = GradeSheet::sole();

    expect($gradeSheet->composition_number)->toBeNull()
        ->and($gradeSheet->term_id)->toBe($this->term->id);
});

test('la période est requise', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->secondaireClassroom->id)
        ->set('subject_id', $this->subject->id)
        ->set('title', 'Devoir 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['term_id']);

    expect(GradeSheet::count())->toBe(0);
});

test('sélectionner une classe pré-remplit le type devoir et le poids 2', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->secondaireClassroom->id)
        ->assertSet('type', 'devoir')
        ->assertSet('weight', 2.0);
});

test('changer le type en interrogation ajuste le poids par défaut à 1', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->secondaireClassroom->id)
        ->set('type', 'interrogation')
        ->assertSet('weight', 1.0);
});

test('la liste des matières est filtrée sur les matières secondaire', function () {
    $primaireOnly = Subject::factory()->create(['is_prescolaire_primaire' => true, 'is_secondaire' => false]);

    $subjects = Livewire::test(Index::class)
        ->set('classroom_id', $this->secondaireClassroom->id)
        ->viewData('subjects');

    expect($subjects->pluck('id'))->toContain($this->subject->id)
        ->not->toContain($primaireOnly->id);
});

test('une matière incompatible avec le cycle secondaire est rejetée côté serveur', function () {
    $primaireOnly = Subject::factory()->create(['is_prescolaire_primaire' => true, 'is_secondaire' => false]);

    Livewire::test(Index::class)
        ->set('classroom_id', $this->secondaireClassroom->id)
        ->set('subject_id', $primaireOnly->id)
        ->set('term_id', $this->term->id)
        ->set('title', 'Devoir 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['subject_id']);

    expect(GradeSheet::count())->toBe(0);
});

test('un enseignant ne voit que ses matières affectées, sans logique classe entière', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    TeacherAssignment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $teacher->id,
        'classroom_id' => $this->secondaireClassroom->id,
        'subject_id' => $this->subject->id,
    ]);
    $otherSubject = Subject::factory()->create(['is_secondaire' => true]);

    $this->actingAs($teacher);

    $subjects = Livewire::test(Index::class)
        ->set('classroom_id', $this->secondaireClassroom->id)
        ->viewData('subjects');

    expect($subjects->pluck('id'))
        ->toContain($this->subject->id)
        ->not->toContain($otherSubject->id);
});

test('un enseignant sans affectation sur la matière est refusé', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(Index::class)
        ->set('classroom_id', $this->secondaireClassroom->id)
        ->set('subject_id', $this->subject->id)
        ->set('term_id', $this->term->id)
        ->set('title', 'Devoir 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertForbidden();

    expect(GradeSheet::count())->toBe(0);
});

test('un éducateur voit le lien Saisir les notes', function () {
    GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => $this->secondaireClassroom->id,
        'subject_id' => $this->subject->id,
        'term_id' => $this->term->id,
    ]);

    Livewire::test(Index::class)->assertSee('Saisir les notes');
});

test('un directeur ne voit pas le lien Saisir les notes pour une feuille qu’il ne peut pas modifier', function () {
    GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => $this->secondaireClassroom->id,
        'subject_id' => $this->subject->id,
        'term_id' => $this->term->id,
    ]);

    $directeur = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($directeur);

    Livewire::test(Index::class)->assertDontSee('Saisir les notes');
});
