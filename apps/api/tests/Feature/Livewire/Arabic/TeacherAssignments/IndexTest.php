<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\TeacherAssignments\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);

    $this->teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->arabicSubject = ArabicSubject::factory()->create();
    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
});

test('un directeur affecte un enseignant à un niveau sans série', function () {
    $this->actingAs($this->directeur);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('user_id', $this->teacher->id)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    $assignment = ArabicTeacherAssignment::sole();

    expect($assignment->arabic_level_id)->toBe($arabicLevel->id)
        ->and($assignment->arabic_serie_id)->toBeNull();
});

test('la série est requise quand le niveau l’exige', function () {
    $this->actingAs($this->directeur);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => true]);

    Livewire::test(Index::class)
        ->set('user_id', $this->teacher->id)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasErrors(['arabic_serie_id']);

    expect(ArabicTeacherAssignment::count())->toBe(0);
});

test('un fondateur d’un établissement indépendant peut affecter un enseignant', function () {
    $founder = createUserWithRole($this->establishment, 'fondateur');
    $this->actingAs($founder);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('user_id', $this->teacher->id)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicTeacherAssignment::count())->toBe(1);
});

test('un enseignant ne peut pas créer d’affectation', function () {
    $this->actingAs($this->teacher);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('user_id', $this->teacher->id)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertForbidden();

    expect(ArabicTeacherAssignment::count())->toBe(0);
});

test('un établissement non arabe n’a pas accès à l’écran', function () {
    $establishment = Establishment::factory()->create(['is_arabe' => false]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($directeur);

    Livewire::test(Index::class)->assertForbidden();
});

test('les affectations de niveaux nécessitant une série ne dupliquent pas en base', function () {
    $this->actingAs($this->directeur);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => true]);
    $arabicSerie = ArabicSerie::factory()->create();

    Livewire::test(Index::class)
        ->set('user_id', $this->teacher->id)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set('arabic_serie_id', $arabicSerie->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save');

    Livewire::test(Index::class)
        ->set('user_id', $this->teacher->id)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set('arabic_serie_id', $arabicSerie->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save');

    expect(ArabicTeacherAssignment::count())->toBe(1);
});
