<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\Serie;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\SubjectCoefficient;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Academics\SubjectCoefficients\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);

    $this->subject = Subject::factory()->create(['establishment_id' => $this->establishment->id]);
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
