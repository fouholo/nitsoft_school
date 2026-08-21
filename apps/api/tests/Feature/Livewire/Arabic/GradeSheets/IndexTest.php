<?php

declare(strict_types=1);

use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\GradeSheets\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    actingInEstablishment($this->establishment);

    $this->arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);
    $this->arabicSubject = ArabicSubject::factory()->create();
    $this->arabicTerm = ArabicTerm::factory()->create(['establishment_id' => $this->establishment->id]);
});

test('un educateur crée une évaluation sans être affecté', function () {
    $educateur = createUserWithRole($this->establishment, 'educateur');
    $this->actingAs($educateur);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $this->arabicLevel->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->set('arabic_term_id', $this->arabicTerm->id)
        ->set('title', 'Devoir 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicGradeSheet::count())->toBe(1);
});

test('un enseignant affecté au groupe peut créer une évaluation', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    ArabicTeacherAssignment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $teacher->id,
        'arabic_level_id' => $this->arabicLevel->id,
        'arabic_subject_id' => $this->arabicSubject->id,
    ]);
    $this->actingAs($teacher);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $this->arabicLevel->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->set('arabic_term_id', $this->arabicTerm->id)
        ->set('title', 'Devoir 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicGradeSheet::count())->toBe(1);
});

test('un enseignant sans affectation est refusé', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $this->arabicLevel->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->set('arabic_term_id', $this->arabicTerm->id)
        ->set('title', 'Devoir 1')
        ->set('graded_on', now()->toDateString())
        ->call('save')
        ->assertForbidden();

    expect(ArabicGradeSheet::count())->toBe(0);
});

test('un directeur ne voit pas le lien Saisir les notes pour une feuille qu’il ne peut pas modifier', function () {
    ArabicGradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_level_id' => $this->arabicLevel->id,
        'arabic_subject_id' => $this->arabicSubject->id,
        'arabic_term_id' => $this->arabicTerm->id,
    ]);

    $directeur = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($directeur);

    Livewire::test(Index::class)->assertDontSee('Saisir les notes');
});

test('un établissement non arabe n’a pas accès à l’écran', function () {
    $establishment = Establishment::factory()->create(['is_arabe' => false]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($directeur);

    Livewire::test(Index::class)->assertForbidden();
});
