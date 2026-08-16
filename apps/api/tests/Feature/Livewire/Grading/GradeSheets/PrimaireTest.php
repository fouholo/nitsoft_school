<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
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
});

test('la création d’une composition pour une classe primaire fonctionne', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->set('composition_number', 1)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(GradeSheet::count())->toBe(1);

    $gradeSheet = GradeSheet::sole();

    expect($gradeSheet->type)->toBe('composition')
        ->and($gradeSheet->term_id)->toBeNull()
        ->and($gradeSheet->primary_subject_id)->toBeNull()
        ->and($gradeSheet->composition_number)->toBe(1);
});

test('le n° de composition est requis', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['composition_number']);

    expect(GradeSheet::count())->toBe(0);
});

test('le titre est requis', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
        ->set('composition_number', 1)
        ->set('title', '')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['title']);

    expect(GradeSheet::count())->toBe(0);
});

test('une classe secondaire est rejetée', function () {
    $secondaireClassroom = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->primaireClassroom->school_year_id,
    ]);

    Livewire::test(Index::class)
        ->set('classroom_id', $secondaireClassroom->id)
        ->set('composition_number', 1)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['classroom_id']);

    expect(GradeSheet::count())->toBe(0);
});

test('un enseignant avec une affectation classe entière peut créer une composition', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    TeacherAssignment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $teacher->id,
        'classroom_id' => $this->primaireClassroom->id,
        'subject_id' => null,
    ]);

    $this->actingAs($teacher);

    Livewire::test(Index::class)
        ->set('classroom_id', $this->primaireClassroom->id)
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
        ->set('composition_number', 1)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertForbidden();

    expect(GradeSheet::count())->toBe(0);
});
