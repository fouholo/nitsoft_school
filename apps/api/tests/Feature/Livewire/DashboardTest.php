<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Establishments\Enums\EstablishmentType;
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

test('les compteurs à zéro affichent un texte explicatif plutôt qu’un chiffre nu', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire::test(Dashboard::class)
        ->assertSee('Aucun élève inscrit')
        ->assertSee('Aucune année scolaire en cours')
        ->assertSee('Aucun solde restant à percevoir');
});

test('le badge de cycle et l’année scolaire courante s’affichent quand disponibles', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $directeur = createUserWithRole($establishment, 'directeur');
    $schoolYear = SchoolYear::factory()->create(['is_current' => true, 'label' => '2026-2027']);
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire::test(Dashboard::class)
        ->assertSee('Secondaire')
        ->assertSee('2026-2027')
        ->assertDontSee('Aucune année scolaire en cours');

    expect($schoolYear->is_current)->toBeTrue();
});

test('un enseignant ne voit pas le raccourci "Suivi des paiements" mais voit les autres', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);
    test()->actingAs($teacher);

    Livewire::test(Dashboard::class)
        ->assertSee('Accès rapides')
        ->assertSee('Élèves')
        ->assertSee('Bulletins')
        ->assertSee('Présences')
        ->assertDontSee('Suivi des paiements');
});
