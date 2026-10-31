<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Teacher;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Livewire\Staff\ManageOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/*
 * Personnel sur plusieurs écoles — voir
 * docs/superpowers/specs/2026-10-31-personnel-plusieurs-ecoles-design.md.
 */

beforeEach(function () {
    $this->foundation = Foundation::factory()->create();
    $this->schoolA = Establishment::factory()->create(['foundation_id' => $this->foundation->id, 'name' => 'Ecole A']);
    $this->schoolB = Establishment::factory()->create(['foundation_id' => $this->foundation->id, 'name' => 'Ecole B']);
    $this->schoolC = Establishment::factory()->create(['foundation_id' => $this->foundation->id, 'name' => 'Ecole C']);

    $this->generalAdmin = createGeneralAdmin($this->foundation);
    $this->actingAs($this->generalAdmin);
});

function schoolStaff(Establishment $school, string $role = 'caissier', bool $isActive = true): EstablishmentUserPivot
{
    $user = User::factory()->create();
    $school->users()->attach($user->id, ['role' => $role, 'is_active' => $isActive]);

    return EstablishmentUserPivot::where('user_id', $user->id)->where('establishment_id', $school->id)->sole();
}

test('un compte créé sur plusieurs écoles reste un seul compte, avec une ligne par école', function () {
    Livewire::test(ManageOrganization::class)
        ->assertSee('Ecole C')
        ->set('staff_name', 'Awa Caisse')
        ->set('staff_email', 'awa.caisse@nitsoft.test')
        ->set('staff_role', 'caissier')
        ->set('staff_establishment_ids', [(string) $this->schoolA->id, (string) $this->schoolB->id])
        ->call('create')
        ->assertHasNoErrors()
        ->assertSet('generatedPasswordFor', 'awa.caisse@nitsoft.test')
        ->assertSet('staff_establishment_ids', []);

    $user = User::where('email', 'awa.caisse@nitsoft.test')->sole();

    expect($user->must_change_password)->toBeTrue()
        ->and($user->roleFor($this->schoolA->id))->toBe('caissier')
        ->and($user->roleFor($this->schoolB->id))->toBe('caissier')
        ->and($user->roleFor($this->schoolC->id))->toBeNull()
        ->and($user->accessibleEstablishments()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->schoolA->id, $this->schoolB->id])->sort()->values()->all());
});

test('il faut cocher au moins une école de l’organisation', function () {
    $outside = Establishment::factory()->create();

    Livewire::test(ManageOrganization::class)
        ->set('staff_name', 'Sans Ecole')
        ->set('staff_email', 'sans.ecole@nitsoft.test')
        ->call('create')
        ->assertHasErrors(['staff_establishment_ids' => 'required'])
        ->assertSee(__('Cochez au moins une école.'));

    Livewire::test(ManageOrganization::class)
        ->set('staff_name', 'Ecole Externe')
        ->set('staff_email', 'ecole.externe@nitsoft.test')
        ->set('staff_establishment_ids', [$this->schoolA->id, $outside->id])
        ->call('create')
        ->assertHasErrors('staff_establishment_ids.1');

    expect(User::whereIn('email', ['sans.ecole@nitsoft.test', 'ecole.externe@nitsoft.test'])->count())->toBe(0);
});

test('un e-mail déjà utilisé renvoie vers l’ajout à d’autres écoles', function () {
    $existing = schoolStaff($this->schoolA);

    Livewire::test(ManageOrganization::class)
        ->set('staff_name', 'Doublon')
        ->set('staff_email', $existing->user->email)
        ->set('staff_establishment_ids', [$this->schoolB->id])
        ->call('create')
        ->assertHasErrors('staff_email')
        ->assertSee("Ajouter à d'autres écoles");

    expect(User::where('email', $existing->user->email)->count())->toBe(1);
});

test('une école indépendante prend son école d’office, quoi qu’envoie le navigateur', function () {
    $school = Establishment::factory()->create(['foundation_id' => null]);
    $other = Establishment::factory()->create();
    $this->actingAs(createGeneralAdmin($school));

    Livewire::test(ManageOrganization::class)
        ->assertDontSeeHtml('wire:model="staff_establishment_ids"')
        ->set('staff_name', 'Seule Ecole')
        ->set('staff_email', 'seule.ecole@nitsoft.test')
        ->set('staff_establishment_ids', [$other->id])
        ->call('create')
        ->assertHasNoErrors();

    expect(EstablishmentUserPivot::where('user_id', User::where('email', 'seule.ecole@nitsoft.test')->value('id'))->sole()->establishment_id)
        ->toBe($school->id);
});

test('un enseignant reçoit une fiche par école, à la création comme à l’ajout', function () {
    Livewire::test(ManageOrganization::class)
        ->set('staff_name', 'Prof Test')
        ->set('staff_email', 'prof.test@nitsoft.test')
        ->set('staff_role', 'enseignant')
        ->set('staff_establishment_ids', [$this->schoolA->id, $this->schoolB->id])
        ->call('create')
        ->assertHasNoErrors();

    $user = User::where('email', 'prof.test@nitsoft.test')->sole();

    expect(Teacher::withoutGlobalScopes()->where('user_id', $user->id)->pluck('establishment_id')->sort()->values()->all())
        ->toBe(collect([$this->schoolA->id, $this->schoolB->id])->sort()->values()->all());

    $pivot = EstablishmentUserPivot::where('user_id', $user->id)->where('establishment_id', $this->schoolA->id)->sole();

    Livewire::test(ManageOrganization::class)
        ->call('startAddingToEstablishments', $pivot->id)
        ->assertSet('add_role', 'enseignant')
        ->set('add_establishment_ids', [$this->schoolC->id])
        ->call('saveAddToEstablishments')
        ->assertHasNoErrors();

    expect(Teacher::withoutGlobalScopes()->where('user_id', $user->id)->where('establishment_id', $this->schoolC->id)->where('name', 'Prof Test')->exists())->toBeTrue();
});

test('une personne existante est ajoutée à une autre école avec un autre rôle, sans changer son mot de passe', function () {
    $pivot = schoolStaff($this->schoolA, 'gestionnaire');
    $user = $pivot->user;
    $user->update(['password' => 'Mot-De-Passe-2026', 'must_change_password' => false]);

    Livewire::test(ManageOrganization::class)
        ->assertSee("Ajouter à d'autres écoles")
        ->call('startAddingToEstablishments', $pivot->id)
        ->assertSet('addingPivotId', $pivot->id)
        ->assertSee('Ecole B')
        ->set('add_role', 'caissier')
        ->set('add_establishment_ids', [(string) $this->schoolB->id])
        ->call('saveAddToEstablishments')
        ->assertHasNoErrors()
        ->assertSet('addingPivotId', null);

    $user->refresh();

    expect($user->roleFor($this->schoolA->id))->toBe('gestionnaire')
        ->and($user->roleFor($this->schoolB->id))->toBe('caissier')
        ->and(Hash::check('Mot-De-Passe-2026', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse();
});

test('l’ajout exige au moins une école', function () {
    $pivot = schoolStaff($this->schoolA);

    Livewire::test(ManageOrganization::class)
        ->call('startAddingToEstablishments', $pivot->id)
        ->call('saveAddToEstablishments')
        ->assertHasErrors(['add_establishment_ids' => 'required']);
});

test('une école où la personne figure déjà est refusée, même inactive', function () {
    $pivot = schoolStaff($this->schoolA);
    $this->schoolB->users()->attach($pivot->user_id, ['role' => 'caissier', 'is_active' => false]);

    foreach ([$this->schoolA->id, $this->schoolB->id] as $schoolId) {
        Livewire::test(ManageOrganization::class)
            ->call('startAddingToEstablishments', $pivot->id)
            ->set('add_role', 'gestionnaire')
            ->set('add_establishment_ids', [$schoolId])
            ->call('saveAddToEstablishments')
            ->assertHasErrors('add_establishment_ids.0');
    }

    expect(EstablishmentUserPivot::where('user_id', $pivot->user_id)->count())->toBe(2);
});

test('l’action disparaît quand toutes les écoles sont occupées', function () {
    $pivot = schoolStaff($this->schoolA);
    $this->schoolB->users()->attach($pivot->user_id, ['role' => 'caissier', 'is_active' => true]);
    $this->schoolC->users()->attach($pivot->user_id, ['role' => 'caissier', 'is_active' => true]);

    Livewire::test(ManageOrganization::class)
        ->assertDontSeeHtml('wire:click="startAddingToEstablishments('.$pivot->id.')"');
});

test('une personne hors de l’organisation, un fondateur ou un parent ne peuvent pas être ajoutés', function () {
    $outsider = schoolStaff(Establishment::factory()->create());
    $parent = schoolStaff($this->schoolA, 'parent');
    $founder = schoolStaff($this->schoolA, 'fondateur');

    expect(fn () => Livewire::test(ManageOrganization::class)->call('startAddingToEstablishments', $outsider->id))
        ->toThrow(ModelNotFoundException::class);

    foreach ([$parent, $founder] as $pivot) {
        Livewire::test(ManageOrganization::class)
            ->assertDontSeeHtml('wire:click="startAddingToEstablishments('.$pivot->id.')"')
            ->call('startAddingToEstablishments', $pivot->id)
            ->assertForbidden();
    }

    expect(fn () => Livewire::test(ManageOrganization::class)
        ->set('addingPivotId', $outsider->id)
        ->set('add_role', 'caissier')
        ->set('add_establishment_ids', [$this->schoolB->id])
        ->call('saveAddToEstablishments'))
        ->toThrow(ModelNotFoundException::class);

    expect(EstablishmentUserPivot::where('user_id', $outsider->user_id)->count())->toBe(1);
});

test('un fondateur qui n’est pas administrateur général ne peut ni créer ni ajouter', function () {
    $pivot = schoolStaff($this->schoolA);

    $otherFounder = User::factory()->create();
    $this->foundation->users()->attach($otherFounder->id, ['role' => 'fondateur', 'is_active' => true]);
    $this->actingAs($otherFounder);

    Livewire::test(ManageOrganization::class)
        ->set('staff_name', 'Refus')
        ->set('staff_email', 'refus@nitsoft.test')
        ->set('staff_establishment_ids', [$this->schoolA->id])
        ->call('create')
        ->assertForbidden();

    Livewire::test(ManageOrganization::class)
        ->call('startAddingToEstablishments', $pivot->id)
        ->assertForbidden();

    expect(User::where('email', 'refus@nitsoft.test')->exists())->toBeFalse();
});

test('une école indépendante n’a pas l’action d’ajout', function () {
    $school = Establishment::factory()->create(['foundation_id' => null]);
    $this->actingAs(createGeneralAdmin($school));
    $pivot = schoolStaff($school);

    Livewire::test(ManageOrganization::class)
        ->assertDontSeeHtml('wire:click="startAddingToEstablishments(')
        ->call('startAddingToEstablishments', $pivot->id)
        ->assertNotFound();
});
