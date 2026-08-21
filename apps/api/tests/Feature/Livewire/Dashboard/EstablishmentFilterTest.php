<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Livewire\Dashboard\EstablishmentFilter;
use Livewire\Livewire;

test('toutes les écoles du groupe sont cochées par défaut', function () {
    $foundation = Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $founder = createFounder($foundation);
    actingInEstablishment($schoolA);
    test()->actingAs($founder);

    $component = Livewire::test(EstablishmentFilter::class)
        ->assertSee($schoolA->name)
        ->assertSee($schoolB->name);

    expect($component->get('selected'))->toEqualCanonicalizing([$schoolA->id, $schoolB->id]);
});

test('changer la sélection dispatch establishment-filter-changed avec les IDs cochés', function () {
    $foundation = Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $founder = createFounder($foundation);
    actingInEstablishment($schoolA);
    test()->actingAs($founder);

    Livewire::test(EstablishmentFilter::class)
        ->set('selected', [$schoolA->id])
        ->assertDispatched('establishment-filter-changed', establishmentIds: [$schoolA->id]);
});
