<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Domain\Establishments\Models\Inspection;
use App\Livewire\Staff\ManageOrganization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('un GENERAL_ADMIN d’une école indépendante peut créer, supprimer et nommer un LOCAL_ADMIN', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    $generalAdmin = createGeneralAdmin($establishment);
    test()->actingAs($generalAdmin);

    Livewire::test(ManageOrganization::class)
        ->set('staff_establishment_id', $establishment->id)
        ->set('staff_name', 'Caissier Test')
        ->set('staff_email', 'caissier.test@nitsoft.test')
        ->set('staff_role', 'caissier')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSet('generatedPasswordFor', 'caissier.test@nitsoft.test');

    $cashier = User::where('email', 'caissier.test@nitsoft.test')->sole();
    $cashierPivot = EstablishmentUserPivot::where('user_id', $cashier->id)->sole();

    $directeur = User::factory()->create();
    $establishment->users()->attach($directeur->id, ['role' => 'directeur', 'is_active' => true]);
    $directeurPivot = EstablishmentUserPivot::where('user_id', $directeur->id)->sole();

    Livewire::test(ManageOrganization::class)->call('nominateLocalAdmin', $directeurPivot->id);
    expect($directeurPivot->fresh()->is_local_admin)->toBeTrue();

    Livewire::test(ManageOrganization::class)->call('dismissLocalAdmin', $establishment->id);
    expect($directeurPivot->fresh()->is_local_admin)->toBeNull();

    Livewire::test(ManageOrganization::class)->call('delete', $cashierPivot->id);
    expect(EstablishmentUserPivot::find($cashierPivot->id))->toBeNull();
});

test('un GENERAL_ADMIN ne peut pas se désactiver ni se supprimer lui-même', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    $generalAdmin = createGeneralAdmin($establishment);
    test()->actingAs($generalAdmin);

    $ownPivot = EstablishmentUserPivot::where('user_id', $generalAdmin->id)->sole();

    Livewire::test(ManageOrganization::class)
        ->call('deactivate', $ownPivot->id)
        ->assertStatus(422);

    Livewire::test(ManageOrganization::class)
        ->call('delete', $ownPivot->id)
        ->assertStatus(422);
});

test('un GENERAL_ADMIN peut céder son pouvoir à un autre fondateur du même établissement indépendant', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    $generalAdmin = createGeneralAdmin($establishment);
    test()->actingAs($generalAdmin);

    $otherFounder = User::factory()->create();
    $establishment->users()->attach($otherFounder->id, ['role' => 'fondateur', 'is_active' => true]);
    $otherPivot = EstablishmentUserPivot::where('user_id', $otherFounder->id)->sole();

    Livewire::test(ManageOrganization::class)->call('cedeGeneralAdmin', $otherPivot->id);

    expect($otherPivot->fresh()->is_general_admin)->toBeTrue()
        ->and(EstablishmentUserPivot::where('user_id', $generalAdmin->id)->sole()->is_general_admin)->toBeNull();
});

test('un fondateur sans pouvoir actuel peut réclamer le GENERAL_ADMIN', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    $generalAdmin = createGeneralAdmin($establishment);

    $challenger = User::factory()->create();
    $establishment->users()->attach($challenger->id, ['role' => 'fondateur', 'is_active' => true]);
    test()->actingAs($challenger);

    Livewire::test(ManageOrganization::class)->call('reclaim');

    expect(EstablishmentUserPivot::where('user_id', $challenger->id)->sole()->is_general_admin)->toBeTrue()
        ->and(EstablishmentUserPivot::where('user_id', $generalAdmin->id)->sole()->is_general_admin)->toBeNull();
});

test('un GENERAL_ADMIN de fondation peut activer un fondateur en attente', function () {
    $foundation = Foundation::factory()->create();
    $generalAdmin = createGeneralAdmin($foundation);
    test()->actingAs($generalAdmin);

    $pendingFounder = User::factory()->create();
    $foundation->users()->attach($pendingFounder->id, ['role' => 'fondateur', 'is_active' => false]);
    $pendingPivot = FoundationUserPivot::where('user_id', $pendingFounder->id)->sole();

    Livewire::test(ManageOrganization::class)->call('activateFondateur', $pendingPivot->id);

    expect($pendingPivot->fresh()->is_active)->toBeTrue();
});

test('un GENERAL_ADMIN de fondation peut créer un établissement avec les champs complémentaires', function () {
    $foundation = Foundation::factory()->create();
    $generalAdmin = createGeneralAdmin($foundation);
    test()->actingAs($generalAdmin);
    Storage::fake('public');

    $inspection = Inspection::create(['codeiep' => 'IEP-TEST', 'inspection_name' => 'Inspection Test']);

    Livewire::test(ManageOrganization::class)
        ->set('new_establishment_name', 'École Test')
        ->set('new_establishment_type', EstablishmentType::Secondaire->value)
        ->set('new_establishment_inspection_id', (string) $inspection->id)
        ->set('new_establishment_opening_code', 'OUV-001')
        ->set('new_establishment_dsps_code', 'DSPS-001')
        ->set('new_establishment_latitude', '5.336400')
        ->set('new_establishment_longitude', '-4.026400')
        ->set('new_establishment_email', 'contact@ecole-test.ci')
        ->set('new_establishment_is_arabe', true)
        ->set('new_establishment_logo', UploadedFile::fake()->image('logo.jpg')->size(50))
        ->call('createEstablishment')
        ->assertHasNoErrors();

    $establishment = Establishment::where('name', 'École Test')->sole();

    expect($establishment->inspection_id)->toBe($inspection->id)
        ->and($establishment->opening_code)->toBe('OUV-001')
        ->and($establishment->dsps_code)->toBe('DSPS-001')
        ->and((float) $establishment->latitude)->toBe(5.3364)
        ->and($establishment->email)->toBe('contact@ecole-test.ci')
        ->and($establishment->is_arabe)->toBeTrue()
        ->and($establishment->logo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($establishment->logo_path);

    expect($establishment->foundation_id)->toBe($foundation->id)
        ->and($establishment->type)->toBe(EstablishmentType::Secondaire)
        ->and($establishment->slug)->toBe('ecole-test');
});

test('un fondateur simple (non GENERAL_ADMIN) ne peut pas créer d’établissement', function () {
    $foundation = Foundation::factory()->create();
    createGeneralAdmin($foundation);
    $plainFounder = createFounder($foundation);
    test()->actingAs($plainFounder);

    Livewire::test(ManageOrganization::class)
        ->set('new_establishment_name', 'École Refusée')
        ->set('new_establishment_type', EstablishmentType::Secondaire->value)
        ->call('createEstablishment')
        ->assertForbidden();

    expect(Establishment::where('name', 'École Refusée')->exists())->toBeFalse();
});

test('créer un établissement depuis une organisation qui est elle-même un établissement indépendant échoue', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    $generalAdmin = createGeneralAdmin($establishment);
    test()->actingAs($generalAdmin);

    Livewire::test(ManageOrganization::class)
        ->set('new_establishment_name', 'École Impossible')
        ->set('new_establishment_type', EstablishmentType::Secondaire->value)
        ->call('createEstablishment')
        ->assertStatus(404);
});

test('un utilisateur sans rôle fondateur ne peut pas accéder à l’écran', function () {
    $establishment = Establishment::factory()->create();
    $teacher = User::factory()->create();
    $establishment->users()->attach($teacher->id, ['role' => 'enseignant', 'is_active' => true]);
    test()->actingAs($teacher);

    Livewire::test(ManageOrganization::class)->assertForbidden();
});

test('un fondateur avec une ancienne ligne foundation_user pointant vers une fondation supprimée accède quand même à sa fondation active', function () {
    $deletedFoundation = Foundation::factory()->create();
    $activeFoundation = Foundation::factory()->create();
    Establishment::factory()->create(['foundation_id' => $activeFoundation->id]);

    $founder = createFounder($deletedFoundation);
    // Résidu d'un ancien groupe supprimé — la ligne foundation_user n'a pas
    // été nettoyée. resolveOrganizationFor() doit l'ignorer plutôt que de
    // planter sur Foundation::findOrFail() (voir le bug corrigé).
    $deletedFoundation->delete();
    $activeFoundation->users()->attach($founder->id, ['role' => 'fondateur', 'is_active' => true]);

    test()->actingAs($founder);

    Livewire::test(ManageOrganization::class)
        ->assertOk()
        ->assertSet('organization.id', $activeFoundation->id);
});
