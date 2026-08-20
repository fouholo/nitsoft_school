<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;

test('un administrateur SaaS sans établissement ne voit pas les liens qui nécessitent un établissement courant', function () {
    $saasAdmin = createSaasAdmin();

    $response = test()->actingAs($saasAdmin)->get('/dashboard');

    $response->assertOk();
    expect(app()->bound('currentEstablishmentId'))->toBeFalse();

    $response->assertSee('Tableau de bord')
        ->assertSee('Groupes scolaires')
        ->assertSee('Établissements')
        ->assertDontSee('Classes')
        ->assertDontSee('Tuteurs')
        ->assertDontSee('Suivi des paiements')
        ->assertDontSee('Tarifs')
        ->assertDontSee('Bulletins')
        ->assertDontSee('Demandes de liaison');
});

test('un directeur avec un établissement courant voit toujours les liens de gestion', function () {
    $establishment = Establishment::factory()->create();
    $director = createUserWithRole($establishment, 'directeur');

    $response = test()->actingAs($director)->get('/dashboard');

    $response->assertOk();
    expect(app()->bound('currentEstablishmentId'))->toBeTrue();

    $response->assertSee('Élèves')
        ->assertSee('Suivi des paiements');
});
