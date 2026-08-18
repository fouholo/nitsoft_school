<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\Serie;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\SubjectCoefficient;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Academics\SubjectCoefficients\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);

    $this->subject = Subject::factory()->create(['is_secondaire' => true]);
});

test('un directeur configure un coefficient pour un niveau sans série', function () {
    $this->actingAs($this->directeur);

    $level = Level::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('level_id', $level->id)
        ->set("coefficients.{$this->subject->id}", '3.5')
        ->call('save')
        ->assertHasNoErrors();

    $coefficient = SubjectCoefficient::sole();

    expect($coefficient->level_id)->toBe($level->id)
        ->and($coefficient->serie_id)->toBeNull()
        ->and($coefficient->subject_id)->toBe($this->subject->id)
        ->and((float) $coefficient->coefficient)->toBe(3.5);
});

test('le coefficient est bien scopé par série quand le niveau l’exige', function () {
    $this->actingAs($this->directeur);

    $level = Level::factory()->terminale()->create();
    $serieA = Serie::factory()->create();
    $serieB = Serie::factory()->create();

    Livewire::test(Index::class)
        ->set('level_id', $level->id)
        ->set('serie_id', $serieA->id)
        ->set("coefficients.{$this->subject->id}", '2')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(Index::class)
        ->set('level_id', $level->id)
        ->set('serie_id', $serieB->id)
        ->set("coefficients.{$this->subject->id}", '5')
        ->call('save')
        ->assertHasNoErrors();

    expect(SubjectCoefficient::count())->toBe(2);

    $coefficientA = SubjectCoefficient::where('serie_id', $serieA->id)->sole();
    $coefficientB = SubjectCoefficient::where('serie_id', $serieB->id)->sole();

    expect((float) $coefficientA->coefficient)->toBe(2.0)
        ->and((float) $coefficientB->coefficient)->toBe(5.0);
});

test('re-enregistrer un coefficient pour le même niveau/matière le remplace', function () {
    $this->actingAs($this->directeur);

    $level = Level::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('level_id', $level->id)
        ->set("coefficients.{$this->subject->id}", '2')
        ->call('save');

    Livewire::test(Index::class)
        ->set('level_id', $level->id)
        ->set("coefficients.{$this->subject->id}", '4')
        ->call('save');

    expect(SubjectCoefficient::count())->toBe(1)
        ->and((float) SubjectCoefficient::sole()->coefficient)->toBe(4.0);
});

test('seules les matières secondaire sont proposées dans la grille', function () {
    $this->actingAs($this->directeur);

    $primaireOnly = Subject::factory()->create(['is_prescolaire_primaire' => true, 'is_secondaire' => false]);

    $level = Level::factory()->create(['requires_series' => false]);

    $subjects = Livewire::test(Index::class)
        ->set('level_id', $level->id)
        ->viewData('subjects');

    expect($subjects->pluck('id'))->toContain($this->subject->id)
        ->not->toContain($primaireOnly->id);
});

test('le sélecteur de niveau ne propose que des niveaux secondaire', function () {
    $this->actingAs($this->directeur);

    $primaireLevel = Level::factory()->primaire()->create();
    $secondaireLevel = Level::factory()->create(['requires_series' => false]);

    $levels = Livewire::test(Index::class)->viewData('levels');

    expect($levels->pluck('id'))->toContain($secondaireLevel->id)
        ->not->toContain($primaireLevel->id);
});

test('un éducateur peut configurer un coefficient', function () {
    $educateur = createUserWithRole($this->establishment, 'educateur');
    $this->actingAs($educateur);

    $level = Level::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('level_id', $level->id)
        ->set("coefficients.{$this->subject->id}", '3.5')
        ->call('save')
        ->assertHasNoErrors();

    $coefficient = SubjectCoefficient::sole();

    expect($coefficient->level_id)->toBe($level->id)
        ->and($coefficient->subject_id)->toBe($this->subject->id)
        ->and((float) $coefficient->coefficient)->toBe(3.5);
});

test('un enseignant peut consulter la grille mais ne peut pas l’enregistrer', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    $level = Level::factory()->create(['requires_series' => false]);

    Livewire::test(Index::class)
        ->set('level_id', $level->id)
        ->set("coefficients.{$this->subject->id}", '2')
        ->call('save')
        ->assertForbidden();

    expect(SubjectCoefficient::count())->toBe(0);
});

test('un établissement préscolaire/primaire n’a pas accès à l’écran', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($directeur);

    Livewire::test(Index::class)->assertForbidden();
});
