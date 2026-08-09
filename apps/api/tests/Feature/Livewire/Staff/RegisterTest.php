<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Livewire\Staff\Register;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

test('un fondateur qui s’inscrit sur une école indépendante sans GENERAL_ADMIN devient GENERAL_ADMIN et est connecté', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);

    Livewire::test(Register::class)
        ->set('name', 'Premier Fondateur')
        ->set('email', 'fondateur1@nitsoft.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid)
        ->set('role', 'fondateur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::where('email', 'fondateur1@nitsoft.test')->sole();
    $pivot = EstablishmentUserPivot::where('establishment_id', $establishment->id)->where('user_id', $user->id)->sole();

    expect($pivot->role)->toBe('fondateur')
        ->and($pivot->is_active)->toBeTrue()
        ->and($pivot->is_general_admin)->toBeTrue()
        ->and(auth()->id())->toBe($user->id);
});

test('un deuxième fondateur sur la même école indépendante reste en attente', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    createGeneralAdmin($establishment);

    Livewire::test(Register::class)
        ->set('name', 'Second Fondateur')
        ->set('email', 'fondateur2@nitsoft.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid)
        ->set('role', 'fondateur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('pendingApproval', true);

    $user = User::where('email', 'fondateur2@nitsoft.test')->sole();
    $pivot = EstablishmentUserPivot::where('establishment_id', $establishment->id)->where('user_id', $user->id)->sole();

    expect($pivot->is_active)->toBeFalse()
        ->and($pivot->is_general_admin)->toBeNull()
        ->and(auth()->check())->toBeFalse();
});

test('un fondateur qui s’inscrit sur une école d’un groupe devient GENERAL_ADMIN de la fondation', function () {
    $foundation = Foundation::factory()->create();
    $establishment = Establishment::factory()->create(['foundation_id' => $foundation->id]);

    Livewire::test(Register::class)
        ->set('name', 'Fondateur Groupe')
        ->set('email', 'fondateur.groupe@nitsoft.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid)
        ->set('role', 'fondateur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::where('email', 'fondateur.groupe@nitsoft.test')->sole();
    $pivot = FoundationUserPivot::where('foundation_id', $foundation->id)->where('user_id', $user->id)->sole();

    expect($pivot->is_active)->toBeTrue()
        ->and($pivot->is_general_admin)->toBeTrue()
        ->and(EstablishmentUserPivot::where('user_id', $user->id)->exists())->toBeFalse();
});

test('un directeur qui s’inscrit sur une école sans LOCAL_ADMIN le devient et est connecté', function () {
    $establishment = Establishment::factory()->create();

    Livewire::test(Register::class)
        ->set('name', 'Premier Directeur')
        ->set('email', 'directeur1@nitsoft.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid)
        ->set('role', 'directeur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::where('email', 'directeur1@nitsoft.test')->sole();
    $pivot = EstablishmentUserPivot::where('establishment_id', $establishment->id)->where('user_id', $user->id)->sole();

    expect($pivot->is_active)->toBeTrue()
        ->and($pivot->is_local_admin)->toBeTrue();
});

test('un deuxième directeur sur la même école reste en attente', function () {
    $establishment = Establishment::factory()->create();
    createLocalAdmin($establishment);

    Livewire::test(Register::class)
        ->set('name', 'Second Directeur')
        ->set('email', 'directeur2@nitsoft.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid)
        ->set('role', 'directeur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('pendingApproval', true);

    $user = User::where('email', 'directeur2@nitsoft.test')->sole();
    $pivot = EstablishmentUserPivot::where('establishment_id', $establishment->id)->where('user_id', $user->id)->sole();

    expect($pivot->is_active)->toBeFalse()
        ->and($pivot->is_local_admin)->toBeNull();
});

test('un uid inconnu est rejeté sans créer de compte', function () {
    Livewire::test(Register::class)
        ->set('name', 'Peu Importe')
        ->set('email', 'peu.importe@nitsoft.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', '000000009999')
        ->set('role', 'directeur')
        ->call('register')
        ->assertHasErrors('uid');

    expect(User::where('email', 'peu.importe@nitsoft.test')->exists())->toBeFalse();
});

test('la contrainte unique is_general_admin empêche un deuxième GENERAL_ADMIN au niveau base', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    createGeneralAdmin($establishment);

    $user = User::factory()->create();

    expect(fn () => EstablishmentUserPivot::create([
        'establishment_id' => $establishment->id,
        'user_id' => $user->id,
        'role' => 'fondateur',
        'is_active' => true,
        'is_general_admin' => true,
    ]))->toThrow(QueryException::class);
});
