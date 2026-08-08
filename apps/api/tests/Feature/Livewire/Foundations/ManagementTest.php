<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Livewire\Foundations\Index;
use App\Livewire\Foundations\Show;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer, renommer et supprimer un groupe scolaire', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'Groupe Test')
        ->call('save')
        ->assertHasNoErrors();

    $foundation = Foundation::sole();

    expect($foundation->name)->toBe('Groupe Test')
        ->and($foundation->slug)->toBe('groupe-test')
        ->and($foundation->is_active)->toBeTrue();

    Livewire::test(Index::class)
        ->call('edit', $foundation->id)
        ->set('name', 'Groupe Test Renommé')
        ->call('save')
        ->assertHasNoErrors();

    expect($foundation->refresh()->name)->toBe('Groupe Test Renommé');

    Livewire::test(Index::class)->call('delete', $foundation->id);

    expect(Foundation::find($foundation->id))->toBeNull();
});

test('un admin d’établissement ne peut pas accéder aux groupes scolaires', function () {
    $admin = createUserWithRole($this->establishment, 'admin');
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});

test('un super admin peut rattacher et détacher un établissement à un groupe', function () {
    $foundation = Foundation::factory()->create();
    $independentSchool = Establishment::factory()->create(['foundation_id' => null]);

    Livewire::test(Show::class, ['foundation' => $foundation])
        ->set('establishment_to_link', $independentSchool->id)
        ->call('linkEstablishment')
        ->assertHasNoErrors();

    expect($independentSchool->refresh()->foundation_id)->toBe($foundation->id);

    Livewire::test(Show::class, ['foundation' => $foundation])
        ->call('unlinkEstablishment', $independentSchool->id);

    expect($independentSchool->refresh()->foundation_id)->toBeNull();
});

test('un super admin peut ajouter un nouveau fondateur puis le retirer', function () {
    $foundation = Foundation::factory()->create();

    Livewire::test(Show::class, ['foundation' => $foundation])
        ->set('founder_name', 'Nouveau Fondateur')
        ->set('founder_email', 'nouveau.fondateur@nitsoft.test')
        ->call('addFounder')
        ->assertHasNoErrors()
        ->assertSet('generatedPasswordFor', 'nouveau.fondateur@nitsoft.test');

    $user = User::where('email', 'nouveau.fondateur@nitsoft.test')->sole();
    $pivot = FoundationUserPivot::where('foundation_id', $foundation->id)->where('user_id', $user->id)->sole();

    expect($pivot->role)->toBe('founder')
        ->and($user->isFounderOf($foundation->id))->toBeTrue();

    Livewire::test(Show::class, ['foundation' => $foundation])
        ->call('removeFounder', $pivot->id);

    expect($user->isFounderOf($foundation->id))->toBeFalse();
});

test('ajouter un fondateur avec un e-mail déjà existant réutilise le compte sans générer de mot de passe', function () {
    $foundation = Foundation::factory()->create();
    $existingUser = User::factory()->create(['email' => 'directeur@nitsoft.test']);

    Livewire::test(Show::class, ['foundation' => $foundation])
        ->set('founder_name', 'Peu importe')
        ->set('founder_email', 'directeur@nitsoft.test')
        ->call('addFounder')
        ->assertHasNoErrors()
        ->assertSet('generatedPassword', null);

    expect(User::where('email', 'directeur@nitsoft.test')->count())->toBe(1)
        ->and($existingUser->isFounderOf($foundation->id))->toBeTrue();
});
