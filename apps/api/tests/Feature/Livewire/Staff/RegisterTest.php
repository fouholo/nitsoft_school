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
        ->set('first_name', 'Jean')
        ->set('email', 'fondateur1@nitsoft.test')
        ->set('pseudo', 'jfondateur1')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid_serveur)
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
        ->set('first_name', 'Marie')
        ->set('email', 'fondateur2@nitsoft.test')
        ->set('pseudo', 'mfondateur2')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid_serveur)
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
        ->set('first_name', 'Paul')
        ->set('email', 'fondateur.groupe@nitsoft.test')
        ->set('pseudo', 'pfondateurgroupe')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid_serveur)
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

test('un fondateur peut s’inscrire directement avec l’UID de la fondation', function () {
    $foundation = Foundation::factory()->create();
    Establishment::factory()->create(['foundation_id' => $foundation->id]);

    Livewire::test(Register::class)
        ->set('name', 'Fondateur Direct')
        ->set('first_name', 'Alice')
        ->set('email', 'fondateur.direct@nitsoft.test')
        ->set('pseudo', 'afondateurdirect')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $foundation->uid_serveur)
        ->set('role', 'fondateur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::where('email', 'fondateur.direct@nitsoft.test')->sole();
    $pivot = FoundationUserPivot::where('foundation_id', $foundation->id)->where('user_id', $user->id)->sole();

    expect($pivot->is_active)->toBeTrue()
        ->and($pivot->is_general_admin)->toBeTrue();
});

test('un deuxième fondateur avec l’UID de la fondation reste en attente', function () {
    $foundation = Foundation::factory()->create();
    $establishment = Establishment::factory()->create(['foundation_id' => $foundation->id]);

    Livewire::test(Register::class)
        ->set('name', 'Premier')
        ->set('first_name', 'Alice')
        ->set('email', 'premier.groupe@nitsoft.test')
        ->set('pseudo', 'apremiergroupe')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid_serveur)
        ->set('role', 'fondateur')
        ->call('register');

    Livewire::test(Register::class)
        ->set('name', 'Second')
        ->set('first_name', 'Bob')
        ->set('email', 'second.groupe@nitsoft.test')
        ->set('pseudo', 'bsecondgroupe')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $foundation->uid_serveur)
        ->set('role', 'fondateur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('pendingApproval', true);

    $user = User::where('email', 'second.groupe@nitsoft.test')->sole();
    $pivot = FoundationUserPivot::where('foundation_id', $foundation->id)->where('user_id', $user->id)->sole();

    expect($pivot->is_active)->toBeFalse()
        ->and($pivot->is_general_admin)->toBeNull();
});

test('un directeur qui saisit l’UID d’une fondation est rejeté', function () {
    $foundation = Foundation::factory()->create();

    Livewire::test(Register::class)
        ->set('name', 'Peu Importe')
        ->set('first_name', 'Peu')
        ->set('email', 'directeur.fondation@nitsoft.test')
        ->set('pseudo', 'directeurfondation')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $foundation->uid_serveur)
        ->set('role', 'directeur')
        ->call('register')
        ->assertHasErrors('uid');

    expect(User::where('email', 'directeur.fondation@nitsoft.test')->exists())->toBeFalse();
});

test('un directeur qui s’inscrit sur une école sans LOCAL_ADMIN le devient et est connecté', function () {
    $establishment = Establishment::factory()->create();

    Livewire::test(Register::class)
        ->set('name', 'Premier Directeur')
        ->set('first_name', 'Éric')
        ->set('email', 'directeur1@nitsoft.test')
        ->set('pseudo', 'edirecteur1')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid_serveur)
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
        ->set('first_name', 'Sophie')
        ->set('email', 'directeur2@nitsoft.test')
        ->set('pseudo', 'sdirecteur2')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid_serveur)
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
        ->set('first_name', 'Peu')
        ->set('email', 'peu.importe@nitsoft.test')
        ->set('pseudo', 'peuimporte')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', '000000009999')
        ->set('role', 'directeur')
        ->call('register')
        ->assertHasErrors('uid');

    expect(User::where('email', 'peu.importe@nitsoft.test')->exists())->toBeFalse();
});

test('prénom et pseudo sont obligatoires à l’inscription', function () {
    $establishment = Establishment::factory()->create();

    Livewire::test(Register::class)
        ->set('name', 'Sans Prénom')
        ->set('email', 'sans.prenom@nitsoft.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid_serveur)
        ->set('role', 'directeur')
        ->call('register')
        ->assertHasErrors(['first_name', 'pseudo']);
});

test('un pseudo déjà pris est rejeté à l’inscription', function () {
    $establishment = Establishment::factory()->create();
    User::factory()->create(['pseudo' => 'dejapris']);

    Livewire::test(Register::class)
        ->set('name', 'Doublon')
        ->set('first_name', 'Jean')
        ->set('email', 'doublon@nitsoft.test')
        ->set('pseudo', 'dejapris')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $establishment->uid_serveur)
        ->set('role', 'directeur')
        ->call('register')
        ->assertHasErrors(['pseudo']);
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
