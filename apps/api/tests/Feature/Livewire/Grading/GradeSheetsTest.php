<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\Term;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use App\Livewire\Grading\GradeSheets\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    // educateur : seul rôle admin-tier habilité à saisir des notes depuis le
    // chantier "privilèges par rôle" (RolePermissions::MATRIX['grades.enter']).
    $this->admin = createUserWithRole($this->establishment, 'educateur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->prescolaireClassroom = Classroom::factory()->prescolaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);
    $this->primaireClassroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);
    $this->secondaireClassroom = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);
    $this->subject = Subject::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->term = Term::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $schoolYear->id]);
});

test('la classe préscolaire est absente du sélecteur de classe', function () {
    $classrooms = Livewire::test(Index::class)->viewData('classrooms');

    expect($classrooms->pluck('id'))->toContain($this->primaireClassroom->id)
        ->not->toContain($this->prescolaireClassroom->id);
});

test('la création d’une feuille de notes pour une classe préscolaire est refusée', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->prescolaireClassroom->id)
        ->set('subject_id', $this->subject->id)
        ->set('term_id', $this->term->id)
        ->set('title', 'Devoir 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['classroom_id']);

    expect(GradeSheet::count())->toBe(0);
});

test('la création d’une feuille de notes pour une classe primaire fonctionne', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->set('subject_id', $this->subject->id)
        ->set('term_id', $this->term->id)
        ->set('title', 'Devoir 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(GradeSheet::count())->toBe(1)
        ->and(GradeSheet::sole()->type)->toBe('composition');
});

test('sélectionner une classe primaire impose le type composition et le poids 1', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->assertSet('type', 'composition')
        ->assertSet('weight', 1.0);
});

test('sélectionner une classe secondaire pré-remplit le type devoir et le poids 2', function () {
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

test('un type incompatible avec le cycle de la classe est rejeté côté serveur', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->set('subject_id', $this->subject->id)
        ->set('term_id', $this->term->id)
        ->set('title', 'Devoir 1')
        ->set('type', 'devoir')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['type']);

    expect(GradeSheet::count())->toBe(0);
});
