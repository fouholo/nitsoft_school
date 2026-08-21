<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\Terms\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
});

test('un directeur crée une période sans dates (numéro de composition)', function () {
    $this->actingAs($this->directeur);

    Livewire::test(Index::class)
        ->set('label', 'Composition 1')
        ->set('sequence', 1)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    $term = ArabicTerm::sole();

    expect($term->starts_on)->toBeNull()
        ->and($term->ends_on)->toBeNull();
});

test('un directeur crée une période avec dates', function () {
    $this->actingAs($this->directeur);

    Livewire::test(Index::class)
        ->set('label', 'Trimestre 1')
        ->set('sequence', 1)
        ->set('starts_on', '2026-09-01')
        ->set('ends_on', '2026-12-01')
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    $term = ArabicTerm::sole();

    expect($term->starts_on?->toDateString())->toBe('2026-09-01');
});

test('une date de fin antérieure à la date de début est rejetée', function () {
    $this->actingAs($this->directeur);

    Livewire::test(Index::class)
        ->set('label', 'Trimestre 1')
        ->set('sequence', 1)
        ->set('starts_on', '2026-12-01')
        ->set('ends_on', '2026-09-01')
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasErrors(['ends_on']);

    expect(ArabicTerm::count())->toBe(0);
});

test('un fondateur d’un établissement indépendant peut configurer une période', function () {
    $founder = createUserWithRole($this->establishment, 'fondateur');
    $this->actingAs($founder);

    Livewire::test(Index::class)
        ->set('label', 'Trimestre 1')
        ->set('sequence', 1)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicTerm::count())->toBe(1);
});

test('un enseignant ne peut pas créer de période', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(Index::class)
        ->set('label', 'Trimestre 1')
        ->set('sequence', 1)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertForbidden();

    expect(ArabicTerm::count())->toBe(0);
});

test('un établissement non arabe n’a pas accès à l’écran', function () {
    $establishment = Establishment::factory()->create(['is_arabe' => false]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($directeur);

    Livewire::test(Index::class)->assertForbidden();
});
