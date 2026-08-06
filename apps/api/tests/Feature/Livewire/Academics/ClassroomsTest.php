<?php

declare(strict_types=1);

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
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
    Livewire::test(Index::class)
        ->set('name', 'Grande Section A')
        ->set('level', 'Grande Section')
        ->set('cycle', Cycle::Prescolaire->value)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    $classroom = Classroom::sole();

    expect($classroom->cycle)->toBe(Cycle::Prescolaire);
});

test('le cycle est requis', function () {
    Livewire::test(Index::class)
        ->set('name', 'Grande Section A')
        ->set('cycle', '')
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasErrors(['cycle']);
});

test('éditer une classe hydrate correctement son cycle', function () {
    $classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    Livewire::test(Index::class)
        ->call('edit', $classroom->id)
        ->assertSet('cycle', Cycle::Primaire->value);
});
