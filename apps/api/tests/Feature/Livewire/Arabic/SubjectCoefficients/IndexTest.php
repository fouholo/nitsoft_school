<?php

declare(strict_types=1);

use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicSubjectCoefficient;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\SubjectCoefficients\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);

    $this->arabicSubject = ArabicSubject::factory()->create();
});

test('un directeur configure un coefficient pour un niveau sans série', function () {
    $this->actingAs($this->directeur);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set("coefficients.{$this->arabicSubject->id}", '3.5')
        ->call('save')
        ->assertHasNoErrors();

    $coefficient = ArabicSubjectCoefficient::sole();

    expect($coefficient->arabic_level_id)->toBe($arabicLevel->id)
        ->and($coefficient->arabic_serie_id)->toBeNull()
        ->and($coefficient->arabic_subject_id)->toBe($this->arabicSubject->id)
        ->and((float) $coefficient->coefficient)->toBe(3.5);
});

test('le coefficient est bien scopé par série quand le niveau l’exige', function () {
    $this->actingAs($this->directeur);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => true]);
    $serieA = ArabicSerie::factory()->create();
    $serieB = ArabicSerie::factory()->create();

    Livewire::test(Index::class)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set('arabic_serie_id', $serieA->id)
        ->set("coefficients.{$this->arabicSubject->id}", '2')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(Index::class)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set('arabic_serie_id', $serieB->id)
        ->set("coefficients.{$this->arabicSubject->id}", '5')
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicSubjectCoefficient::count())->toBe(2);

    $coefficientA = ArabicSubjectCoefficient::where('arabic_serie_id', $serieA->id)->sole();
    $coefficientB = ArabicSubjectCoefficient::where('arabic_serie_id', $serieB->id)->sole();

    expect((float) $coefficientA->coefficient)->toBe(2.0)
        ->and((float) $coefficientB->coefficient)->toBe(5.0);
});

test('re-enregistrer un coefficient pour le même niveau/matière le remplace', function () {
    $this->actingAs($this->directeur);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set("coefficients.{$this->arabicSubject->id}", '2')
        ->call('save');

    Livewire::test(Index::class)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set("coefficients.{$this->arabicSubject->id}", '4')
        ->call('save');

    expect(ArabicSubjectCoefficient::count())->toBe(1)
        ->and((float) ArabicSubjectCoefficient::sole()->coefficient)->toBe(4.0);
});

test('un fondateur d’un établissement indépendant (sans Foundation) peut configurer un coefficient', function () {
    // Régression : hasAdminRightsOn() ne reconnaît un fondateur que via une
    // Foundation (isFounderOfEstablishment) — un fondateur rattaché en
    // direct à un établissement indépendant (comme ici) doit quand même
    // pouvoir enregistrer, d'où la vérification par currentRole() dans la
    // Policy plutôt que par isAdminOfCurrentEstablishment().
    $founder = createUserWithRole($this->establishment, 'fondateur');
    $this->actingAs($founder);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set("coefficients.{$this->arabicSubject->id}", '3.5')
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicSubjectCoefficient::count())->toBe(1);
});

test('un gestionnaire peut configurer un coefficient', function () {
    $gestionnaire = createUserWithRole($this->establishment, 'gestionnaire');
    $this->actingAs($gestionnaire);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set("coefficients.{$this->arabicSubject->id}", '3.5')
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicSubjectCoefficient::count())->toBe(1);
});

test('un éducateur peut consulter la grille mais ne peut pas l’enregistrer (contrairement au français)', function () {
    $educateur = createUserWithRole($this->establishment, 'educateur');
    $this->actingAs($educateur);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set("coefficients.{$this->arabicSubject->id}", '2')
        ->call('save')
        ->assertForbidden();

    expect(ArabicSubjectCoefficient::count())->toBe(0);
});

test('un enseignant peut consulter la grille mais ne peut pas l’enregistrer', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set("coefficients.{$this->arabicSubject->id}", '2')
        ->call('save')
        ->assertForbidden();

    expect(ArabicSubjectCoefficient::count())->toBe(0);
});

test('un établissement non arabe n’a pas accès à l’écran', function () {
    $establishment = Establishment::factory()->create(['is_arabe' => false]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($directeur);

    Livewire::test(Index::class)->assertForbidden();
});

test('les coefficients restent propres à chaque établissement alors que le catalogue est partagé', function () {
    $otherEstablishment = Establishment::factory()->create(['is_arabe' => true]);
    $otherDirecteur = createUserWithRole($otherEstablishment, 'directeur');

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);

    $this->actingAs($this->directeur);
    Livewire::test(Index::class)
        ->set('arabic_level_id', $arabicLevel->id)
        ->set("coefficients.{$this->arabicSubject->id}", '2')
        ->call('save');

    actingInEstablishment($otherEstablishment);
    $this->actingAs($otherDirecteur);

    // Même catalogue global (niveau/matière), mais aucun coefficient hérité
    // de l'autre établissement.
    $coefficients = Livewire::test(Index::class)
        ->set('arabic_level_id', $arabicLevel->id)
        ->viewData('arabicSubjects');

    expect($coefficients->pluck('id'))->toContain($this->arabicSubject->id)
        ->and(ArabicSubjectCoefficient::where('establishment_id', $otherEstablishment->id)->count())->toBe(0)
        ->and(ArabicSubjectCoefficient::withoutTenant()->where('establishment_id', $this->establishment->id)->count())->toBe(1);
});
