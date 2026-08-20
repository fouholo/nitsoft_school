<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Teacher;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Livewire\Staff\Index;
use App\Models\User;
use Livewire\Livewire;

test('un LOCAL_ADMIN peut créer un enseignant avec mot de passe généré, et une fiche Teacher est créée', function () {
    $establishment = Establishment::factory()->create();
    $localAdmin = createLocalAdmin($establishment);
    test()->actingAs($localAdmin);

    Livewire::test(Index::class, ['establishment' => $establishment])
        ->set('staff_name', 'Enseignant Test')
        ->set('staff_email', 'enseignant.test@nitsoft.test')
        ->set('staff_role', 'enseignant')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSet('generatedPasswordFor', 'enseignant.test@nitsoft.test');

    $user = User::where('email', 'enseignant.test@nitsoft.test')->sole();
    $pivot = EstablishmentUserPivot::where('establishment_id', $establishment->id)->where('user_id', $user->id)->sole();

    expect($pivot->role)->toBe('enseignant')
        ->and($pivot->is_active)->toBeTrue();

    $teacher = Teacher::where('user_id', $user->id)->sole();

    expect($teacher->establishment_id)->toBe($establishment->id)
        ->and($teacher->name)->toBe('Enseignant Test')
        ->and($teacher->uid_serveur)->toMatch('/^222\d{9}$/');
});

test('un LOCAL_ADMIN qui crée un caissier ne crée aucune fiche Teacher', function () {
    $establishment = Establishment::factory()->create();
    $localAdmin = createLocalAdmin($establishment);
    test()->actingAs($localAdmin);

    Livewire::test(Index::class, ['establishment' => $establishment])
        ->set('staff_name', 'Caissier Test')
        ->set('staff_email', 'caissier.test@nitsoft.test')
        ->set('staff_role', 'caissier')
        ->call('create')
        ->assertHasNoErrors();

    $user = User::where('email', 'caissier.test@nitsoft.test')->sole();

    expect(Teacher::where('user_id', $user->id)->exists())->toBeFalse();
});

test('un LOCAL_ADMIN peut créer un gestionnaire', function () {
    $establishment = Establishment::factory()->create();
    $localAdmin = createLocalAdmin($establishment);
    test()->actingAs($localAdmin);

    Livewire::test(Index::class, ['establishment' => $establishment])
        ->set('staff_name', 'Gestionnaire Test')
        ->set('staff_email', 'gestionnaire.test@nitsoft.test')
        ->set('staff_role', 'gestionnaire')
        ->call('create')
        ->assertHasNoErrors();

    $user = User::where('email', 'gestionnaire.test@nitsoft.test')->sole();
    $pivot = EstablishmentUserPivot::where('establishment_id', $establishment->id)->where('user_id', $user->id)->sole();

    expect($pivot->role)->toBe('gestionnaire')
        ->and(Teacher::where('user_id', $user->id)->exists())->toBeFalse();
});

test('un LOCAL_ADMIN ne peut pas créer un directeur depuis cet écran', function () {
    $establishment = Establishment::factory()->create();
    $localAdmin = createLocalAdmin($establishment);
    test()->actingAs($localAdmin);

    Livewire::test(Index::class, ['establishment' => $establishment])
        ->set('staff_name', 'Directeur Test')
        ->set('staff_email', 'directeur.test@nitsoft.test')
        ->set('staff_role', 'directeur')
        ->call('create')
        ->assertHasErrors(['staff_role']);

    expect(User::where('email', 'directeur.test@nitsoft.test')->exists())->toBeFalse();
});

test('un LOCAL_ADMIN peut activer et désactiver un compte', function () {
    $establishment = Establishment::factory()->create();
    $localAdmin = createLocalAdmin($establishment);
    test()->actingAs($localAdmin);

    $teacher = User::factory()->create();
    $establishment->users()->attach($teacher->id, ['role' => 'enseignant', 'is_active' => true]);
    $pivot = EstablishmentUserPivot::where('user_id', $teacher->id)->sole();

    Livewire::test(Index::class, ['establishment' => $establishment])
        ->call('deactivate', $pivot->id);

    expect($pivot->fresh()->is_active)->toBeFalse();

    Livewire::test(Index::class, ['establishment' => $establishment])
        ->call('activate', $pivot->id);

    expect($pivot->fresh()->is_active)->toBeTrue();
});

test('un enseignant (sans pouvoir) ne peut pas accéder à l’écran', function () {
    $establishment = Establishment::factory()->create();
    $teacher = User::factory()->create();
    $establishment->users()->attach($teacher->id, ['role' => 'enseignant', 'is_active' => true]);
    test()->actingAs($teacher);

    Livewire::test(Index::class, ['establishment' => $establishment])->assertForbidden();
});

test('un GENERAL_ADMIN de l’établissement indépendant peut aussi accéder à l’écran LOCAL_ADMIN', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    $generalAdmin = createGeneralAdmin($establishment);
    test()->actingAs($generalAdmin);

    Livewire::test(Index::class, ['establishment' => $establishment])->assertOk();
});
