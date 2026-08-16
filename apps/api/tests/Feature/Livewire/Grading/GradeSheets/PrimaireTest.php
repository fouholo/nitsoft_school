<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use App\Livewire\Grading\GradeSheets\Primaire\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->directeur);
});

test('un directeur peut créer une composition, commune à toutes les classes', function () {
    Livewire::test(Index::class)
        ->set('composition_number', 1)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(GradeSheet::count())->toBe(1);

    $gradeSheet = GradeSheet::sole();

    expect($gradeSheet->type)->toBe('composition')
        ->and($gradeSheet->term_id)->toBeNull()
        ->and($gradeSheet->classroom_id)->toBeNull()
        ->and($gradeSheet->primary_subject_id)->toBeNull()
        ->and($gradeSheet->composition_number)->toBe(1);
});

test('le n° de composition est requis', function () {
    Livewire::test(Index::class)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['composition_number']);

    expect(GradeSheet::count())->toBe(0);
});

test('le titre est requis', function () {
    Livewire::test(Index::class)
        ->set('composition_number', 1)
        ->set('title', '')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['title']);

    expect(GradeSheet::count())->toBe(0);
});

test('un educateur ne peut pas créer de composition', function () {
    $educateur = createUserWithRole($this->establishment, 'educateur');
    $this->actingAs($educateur);

    Livewire::test(Index::class)
        ->set('composition_number', 1)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertForbidden();

    expect(GradeSheet::count())->toBe(0);
});

test('un enseignant ne peut pas créer de composition', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(Index::class)
        ->set('composition_number', 1)
        ->set('title', 'Composition 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertForbidden();

    expect(GradeSheet::count())->toBe(0);
});

test('un educateur peut consulter la liste des compositions sans pouvoir en créer', function () {
    $educateur = createUserWithRole($this->establishment, 'educateur');
    $this->actingAs($educateur);

    $isDirecteur = Livewire::test(Index::class)->viewData('isDirecteur');

    expect($isDirecteur)->toBeFalse();
});
