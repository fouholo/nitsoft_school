<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Term;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\ReportCard;
use App\Livewire\Grading\ReportCards\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->admin = createUserWithRole($this->establishment, 'admin');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->prescolaireClassroom = Classroom::factory()->prescolaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);
    $this->term = Term::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $schoolYear->id]);
});

test('la classe préscolaire est absente du sélecteur de classe', function () {
    $classrooms = Livewire::test(Index::class)->viewData('classrooms');

    expect($classrooms->pluck('id'))->not->toContain($this->prescolaireClassroom->id);
});

test('la génération de bulletin pour une classe préscolaire est refusée', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->prescolaireClassroom->id)
        ->set('term_id', $this->term->id)
        ->call('generate')
        ->assertHasErrors(['classroom_id']);

    expect(ReportCard::count())->toBe(0);
});
