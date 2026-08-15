<?php

declare(strict_types=1);

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Serie;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Academics\Classrooms\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $this->admin = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $this->schoolYear = SchoolYear::factory()->create(['establishment_id' => $this->establishment->id]);
});

test('une classe peut être créée avec un cycle préscolaire', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($admin);
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);

    $level = Level::factory()->prescolaire()->create(['level_wording' => 'Grande Section']);

    Livewire::test(Index::class)
        ->set('cycle', Cycle::Prescolaire->value)
        ->set('level_id', $level->id)
        ->set('numero', 'A')
        ->set('school_year_id', $schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    $classroom = Classroom::sole();

    expect($classroom->level->cycle)->toBe(Cycle::Prescolaire)
        ->and($classroom->name)->toBe('Grande Section A');
});

test('le numéro n’est pas exigé et le nom de la classe se compose sans lui', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($admin);
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);

    $level = Level::factory()->prescolaire()->create(['level_wording' => 'Grande Section']);

    Livewire::test(Index::class)
        ->set('cycle', Cycle::Prescolaire->value)
        ->set('level_id', $level->id)
        ->set('numero', '')
        ->set('school_year_id', $schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    $classroom = Classroom::sole();

    expect($classroom->name)->toBe('Grande Section');
});

test('le numéro absent n’empêche pas la série de s’afficher dans le nom', function () {
    $terminale = Level::factory()->terminale()->create();
    $serie = Serie::factory()->create(['serie' => 'C']);

    Livewire::test(Index::class)
        ->set('cycle', Cycle::Secondaire->value)
        ->set('level_id', $terminale->id)
        ->set('serie_id', $serie->id)
        ->set('numero', '')
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    $classroom = Classroom::sole();

    expect($classroom->name)->toBe('Terminale C');
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

test('un niveau dont le cycle n’est pas autorisé pour ce type d’établissement est rejeté', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($admin);
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);

    $secondaireLevel = Level::factory()->terminale()->create();

    Livewire::test(Index::class)
        ->set('cycle', Cycle::Secondaire->value)
        ->set('level_id', $secondaireLevel->id)
        ->set('serie_id', Serie::factory()->create()->id)
        ->set('numero', '1')
        ->set('school_year_id', $schoolYear->id)
        ->call('save')
        ->assertHasErrors(['level_id']);

    expect(Classroom::where('level_id', $secondaireLevel->id)->count())->toBe(0);
});

test('éditer une classe hydrate correctement son niveau', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($admin);
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);

    $classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);

    Livewire::test(Index::class)
        ->call('edit', $classroom->id)
        ->assertSet('cycle', Cycle::Primaire->value)
        ->assertSet('level_id', $classroom->level_id)
        ->assertSet('numero', $classroom->numero);
});
