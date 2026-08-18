<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

test('un fondateur dont la fondation a été supprimée n’a plus d’établissement accessible mais peut quand même se connecter', function () {
    $foundation = Foundation::factory()->create();
    Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $founder = createFounder($foundation);
    $foundation->delete();

    $response = test()->actingAs($founder)->get('/dashboard');

    $response->assertOk();
    expect(app()->bound('currentEstablishmentId'))->toBeFalse();
});

test('un établissement mémorisé en session mais devenu inaccessible est remplacé par un établissement accessible, sans planter', function () {
    $establishment = Establishment::factory()->create();
    $user = createUserWithRole($establishment, 'directeur');

    $otherFoundation = Foundation::factory()->create();
    $newEstablishment = Establishment::factory()->create(['foundation_id' => $otherFoundation->id]);
    $founder = createFounder($otherFoundation);

    // Simule une session qui pointe encore vers un établissement auquel
    // l'utilisateur a depuis perdu l'accès (ex : retiré du staff).
    test()->actingAs($founder);
    test()->withSession(['current_establishment_id' => $establishment->id]);

    $response = test()->get('/dashboard');

    $response->assertOk();
    expect(app('currentEstablishmentId'))->toBe($newEstablishment->id)
        ->and(session('current_establishment_id'))->toBe($newEstablishment->id);
});

test('la connexion complète d’un fondateur avec plusieurs écoles dans son groupe fonctionne de bout en bout', function () {
    $foundation = Foundation::factory()->create();
    Establishment::factory()->count(2)->create(['foundation_id' => $foundation->id]);

    $user = User::factory()->create(['password' => bcrypt('secret-password')]);
    $foundation->users()->attach($user->id, ['role' => 'fondateur', 'is_active' => true]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'secret-password')
        ->call('login')
        ->assertRedirect(route('home'));

    test()->assertAuthenticatedAs($user);

    test()->get('/dashboard')->assertOk();
});
