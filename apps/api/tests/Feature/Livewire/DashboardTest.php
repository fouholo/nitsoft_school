<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Livewire\Dashboard;
use Livewire\Livewire;

test('un utilisateur sans établissement accessible voit un message au lieu d’une erreur', function () {
    $foundation = Foundation::factory()->create();
    Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $founder = createFounder($foundation);
    $foundation->delete();
    test()->actingAs($founder);

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee('Aucun établissement ne vous est actuellement accessible.');
});

test('un directeur avec un établissement accessible voit le tableau de bord normal', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertDontSee('Aucun établissement ne vous est actuellement accessible.')
        ->assertSee('Tableau de bord');
});
