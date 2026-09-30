<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Livewire\Staff\Register;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function fillStaffRegistration(string $email, string $pseudo, string $uid, string $role): Testable
{
    return Livewire::test(Register::class)
        ->set('name', 'Nom')
        ->set('first_name', 'Prénom')
        ->set('email', $email)
        ->set('pseudo', $pseudo)
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('uid', $uid)
        ->set('role', $role);
}

test('un fondateur qui s’inscrit sur une école indépendante sans administrateur reste en attente, sans pouvoir', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);

    fillStaffRegistration('fondateur1@nitsoft.test', 'jfondateur1', $establishment->uid_serveur, 'fondateur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('pendingApproval', true)
        ->assertNoRedirect();

    $user = User::where('email', 'fondateur1@nitsoft.test')->sole();
    $pivot = EstablishmentUserPivot::where('establishment_id', $establishment->id)->where('user_id', $user->id)->sole();

    expect($pivot->role)->toBe('fondateur')
        ->and($pivot->is_active)->toBeFalse()
        ->and($pivot->is_general_admin)->toBeNull()
        ->and($user->accessibleEstablishments())->toBeEmpty()
        ->and(auth()->check())->toBeFalse();
});

test('un deuxième fondateur sur la même école indépendante reste en attente', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    createGeneralAdmin($establishment);

    fillStaffRegistration('fondateur2@nitsoft.test', 'mfondateur2', $establishment->uid_serveur, 'fondateur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('pendingApproval', true);

    $user = User::where('email', 'fondateur2@nitsoft.test')->sole();
    $pivot = EstablishmentUserPivot::where('establishment_id', $establishment->id)->where('user_id', $user->id)->sole();

    expect($pivot->is_active)->toBeFalse()
        ->and($pivot->is_general_admin)->toBeNull()
        ->and(auth()->check())->toBeFalse();
});

test('un fondateur qui s’inscrit sur une école d’un groupe est rattaché à la fondation, en attente', function () {
    $foundation = Foundation::factory()->create();
    $establishment = Establishment::factory()->create(['foundation_id' => $foundation->id]);

    fillStaffRegistration('fondateur.groupe@nitsoft.test', 'pfondateurgroupe', $establishment->uid_serveur, 'fondateur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('pendingApproval', true);

    $user = User::where('email', 'fondateur.groupe@nitsoft.test')->sole();
    $pivot = FoundationUserPivot::where('foundation_id', $foundation->id)->where('user_id', $user->id)->sole();

    expect($pivot->is_active)->toBeFalse()
        ->and($pivot->is_general_admin)->toBeNull()
        ->and(EstablishmentUserPivot::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(auth()->check())->toBeFalse();
});

test('un fondateur peut s’inscrire directement avec l’UID de la fondation, en attente', function () {
    $foundation = Foundation::factory()->create();
    Establishment::factory()->create(['foundation_id' => $foundation->id]);

    fillStaffRegistration('fondateur.direct@nitsoft.test', 'afondateurdirect', $foundation->uid_serveur, 'fondateur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('pendingApproval', true);

    $user = User::where('email', 'fondateur.direct@nitsoft.test')->sole();
    $pivot = FoundationUserPivot::where('foundation_id', $foundation->id)->where('user_id', $user->id)->sole();

    expect($pivot->is_active)->toBeFalse()
        ->and($pivot->is_general_admin)->toBeNull();
});

test('un directeur qui saisit l’UID d’une fondation est rejeté', function () {
    $foundation = Foundation::factory()->create();

    fillStaffRegistration('directeur.fondation@nitsoft.test', 'directeurfondation', $foundation->uid_serveur, 'directeur')
        ->call('register')
        ->assertHasErrors('uid');

    expect(User::where('email', 'directeur.fondation@nitsoft.test')->exists())->toBeFalse();
});

test('un directeur qui s’inscrit sur une école sans administrateur local reste en attente, sans pouvoir', function () {
    $establishment = Establishment::factory()->create();

    fillStaffRegistration('directeur1@nitsoft.test', 'edirecteur1', $establishment->uid_serveur, 'directeur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('pendingApproval', true)
        ->assertNoRedirect();

    $user = User::where('email', 'directeur1@nitsoft.test')->sole();
    $pivot = EstablishmentUserPivot::where('establishment_id', $establishment->id)->where('user_id', $user->id)->sole();

    expect($pivot->is_active)->toBeFalse()
        ->and($pivot->is_local_admin)->toBeNull()
        ->and($user->roleFor($establishment->id))->toBeNull()
        ->and(auth()->check())->toBeFalse();
});

test('un deuxième directeur sur la même école reste en attente', function () {
    $establishment = Establishment::factory()->create();
    createLocalAdmin($establishment);

    fillStaffRegistration('directeur2@nitsoft.test', 'sdirecteur2', $establishment->uid_serveur, 'directeur')
        ->call('register')
        ->assertHasNoErrors()
        ->assertSet('pendingApproval', true);

    $user = User::where('email', 'directeur2@nitsoft.test')->sole();
    $pivot = EstablishmentUserPivot::where('establishment_id', $establishment->id)->where('user_id', $user->id)->sole();

    expect($pivot->is_active)->toBeFalse()
        ->and($pivot->is_local_admin)->toBeNull();
});

test('un uid inconnu est rejeté sans créer de compte', function () {
    fillStaffRegistration('peu.importe@nitsoft.test', 'peuimporte', '000000009999', 'directeur')
        ->call('register')
        ->assertHasErrors('uid');

    expect(User::where('email', 'peu.importe@nitsoft.test')->exists())->toBeFalse();
});

test('les tentatives d’inscription sont limitées', function () {
    foreach (range(1, 5) as $attempt) {
        fillStaffRegistration("essai{$attempt}@nitsoft.test", "essai{$attempt}", '000000009999', 'directeur')
            ->call('register')
            ->assertHasErrors('uid');
    }

    $establishment = Establishment::factory()->create();

    fillStaffRegistration('essai6@nitsoft.test', 'essai6', $establishment->uid_serveur, 'directeur')
        ->call('register')
        ->assertHasErrors('uid')
        ->assertSet('pendingApproval', false);

    expect(User::where('email', 'essai6@nitsoft.test')->exists())->toBeFalse();
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

    fillStaffRegistration('doublon@nitsoft.test', 'dejapris', $establishment->uid_serveur, 'directeur')
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
