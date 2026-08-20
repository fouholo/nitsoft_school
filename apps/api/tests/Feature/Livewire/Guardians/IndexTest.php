<?php

declare(strict_types=1);

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Guardians\Index;
use Livewire\Livewire;

test('la liste des tuteurs se charge sans erreur et ne montre que les liens approuvés de l’établissement courant', function () {
    // Régression : whereHas('students', fn ($q) => $q->wherePivot(...)) plantait
    // en SQL ("Column not found: pivot") — wherePivot() n'existe pas sur le
    // Builder reçu par la closure whereHas(), contrairement à un vrai objet
    // de relation BelongsToMany.
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $approvedGuardian = Guardian::factory()->create(['last_name' => 'Approuvé']);
    $pendingGuardian = Guardian::factory()->create(['last_name' => 'EnAttente']);

    $student->guardians()->attach([
        $approvedGuardian->id => ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Approved],
        $pendingGuardian->id => ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Pending],
    ]);

    $guardians = Livewire::test(Index::class)->viewData('guardians');

    expect($guardians->pluck('id'))->toContain($approvedGuardian->id)
        ->not->toContain($pendingGuardian->id);
});

test('cocher le compte portail sans e-mail bloque l’enregistrement avec une erreur visible plutôt que de fermer le formulaire silencieusement', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire::test(Index::class)
        ->call('create')
        ->set('first_name', 'Awa')
        ->set('last_name', 'Koné')
        ->set('createPortalAccount', true)
        ->call('save')
        ->assertHasErrors(['email' => 'required_if'])
        ->assertSet('showForm', true);

    expect(Guardian::where('first_name', 'Awa')->exists())->toBeFalse();
});

test('créer un tuteur sans lien élève affiche une notice explicative plutôt que de disparaître silencieusement de la liste', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire::test(Index::class)
        ->call('create')
        ->set('first_name', 'Awa')
        ->set('last_name', 'Koné')
        ->call('save')
        ->assertSee('Koné Awa')
        ->assertSee('a été créé');

    expect(Guardian::where('first_name', 'Awa')->exists())->toBeTrue();
});

test('le compte portail créé reçoit le mot de passe par défaut, affiché jusqu’à fermeture manuelle', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $guardian = Guardian::factory()->create(['email' => 'parent@example.test']);
    $student->guardians()->attach($guardian->id, [
        'establishment_id' => $establishment->id,
        'status' => GuardianLinkStatus::Approved,
    ]);

    $component = Livewire::test(Index::class)
        ->call('edit', $guardian->id)
        ->set('createPortalAccount', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('generatedPasswordFor', 'parent@example.test')
        ->assertSet('generatedPassword', \App\Models\User::DEFAULT_PASSWORD);

    $guardian->refresh();
    $user = \App\Models\User::findOrFail($guardian->user_id);
    expect(\Illuminate\Support\Facades\Hash::check(\App\Models\User::DEFAULT_PASSWORD, $user->password))->toBeTrue();

    $component->call('dismissGeneratedPassword')->assertSet('generatedPassword', null);
});
