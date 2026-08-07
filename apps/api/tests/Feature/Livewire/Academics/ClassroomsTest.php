<?php

declare(strict_types=1);

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Serie;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Academics\Classrooms\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->admin = createUserWithRole($this->establishment, 'admin');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $this->schoolYear = SchoolYear::factory()->create(['establishment_id' => $this->establishment->id]);
});

test('une classe peut être créée avec un cycle préscolaire', function () {
    $level = Level::factory()->prescolaire()->create(['level_wording' => 'Grande Section']);

    Livewire::test(Index::class)
        ->set('cycle', Cycle::Prescolaire->value)
        ->set('level_id', $level->id)
        ->set('numero', 'A')
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    $classroom = Classroom::sole();

    expect($classroom->level->cycle)->toBe(Cycle::Prescolaire)
        ->and($classroom->name)->toBe('Grande Section A');
});

test('le niveau est requis', function () {
    Livewire::test(Index::class)
        ->set('level_id', null)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasErrors(['level_id']);
});

test('la série est requise quand le niveau l’exige', function () {
    $terminale = Level::factory()->terminale()->create();

    Livewire::test(Index::class)
        ->set('cycle', Cycle::Secondaire->value)
        ->set('level_id', $terminale->id)
        ->set('serie_id', null)
        ->set('numero', '1')
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasErrors(['serie_id']);
});

test('une classe de terminale avec série se compose correctement', function () {
    $terminale = Level::factory()->terminale()->create();
    $serie = Serie::factory()->create(['serie' => 'C']);

    Livewire::test(Index::class)
        ->set('cycle', Cycle::Secondaire->value)
        ->set('level_id', $terminale->id)
        ->set('serie_id', $serie->id)
        ->set('numero', '1')
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    $classroom = Classroom::sole();

    expect($classroom->name)->toBe('Terminale C 1');
});

test('éditer une classe hydrate correctement son niveau', function () {
    $classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    Livewire::test(Index::class)
        ->call('edit', $classroom->id)
        ->assertSet('cycle', Cycle::Primaire->value)
        ->assertSet('level_id', $classroom->level_id)
        ->assertSet('numero', $classroom->numero);
});
