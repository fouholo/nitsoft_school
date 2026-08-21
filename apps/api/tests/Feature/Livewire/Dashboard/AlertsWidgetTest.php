<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Livewire\Dashboard\AlertsWidget;
use Livewire\Livewire;

test('aucune alerte ne s’affiche quand tout va bien', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire::test(AlertsWidget::class)->assertViewHas('items', []);
});

test('une classe sans enseignant remonte dans les alertes de l’établissement courant', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);

    Livewire::test(AlertsWidget::class)
        ->assertViewHas('items', fn (array $items) => count($items) === 1 && $items[0]['type'] === 'classroom_without_teacher')
        ->assertViewHas('showEstablishmentTag', false);
});

test('un fondateur multi-écoles voit le tag établissement quand plusieurs écoles ont des alertes', function () {
    $foundation = Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id, 'name' => 'École A']);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id, 'name' => 'École B']);
    $founder = createFounder($foundation);
    actingInEstablishment($schoolA);
    test()->actingAs($founder);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    Classroom::factory()->create(['establishment_id' => $schoolA->id, 'school_year_id' => $schoolYear->id]);
    Classroom::factory()->create(['establishment_id' => $schoolB->id, 'school_year_id' => $schoolYear->id]);

    Livewire::test(AlertsWidget::class)
        ->assertViewHas('items', fn (array $items) => count($items) === 2)
        ->assertViewHas('showEstablishmentTag', true);
});
