<?php

declare(strict_types=1);

use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Account\ChangePassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('un utilisateur peut changer son mot de passe en confirmant l’ancien', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'password')
        ->set('password', 'nouveaumdp')
        ->set('password_confirmation', 'nouveaumdp')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('changed', true);

    expect(Hash::check('nouveaumdp', $directeur->fresh()->password))->toBeTrue();
});

test('un mot de passe actuel incorrect bloque le changement', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $originalHash = $directeur->password;

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'mauvais-mot-de-passe')
        ->set('password', 'nouveaumdp')
        ->set('password_confirmation', 'nouveaumdp')
        ->call('save')
        ->assertHasErrors(['current_password']);

    expect($directeur->fresh()->password)->toBe($originalHash);
});

test('une confirmation qui ne correspond pas bloque le changement', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'password')
        ->set('password', 'nouveaumdp')
        ->set('password_confirmation', 'autrechose')
        ->call('save')
        ->assertHasErrors(['password']);
});

test('un parent (portail) accède à l’écran avec le gabarit du portail parents', function () {
    $parentUser = User::factory()->create(['password' => 'azerty']);
    Guardian::factory()->create(['user_id' => $parentUser->id]);
    test()->actingAs($parentUser);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'azerty')
        ->set('password', 'nouveaumdp')
        ->set('password_confirmation', 'nouveaumdp')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('nouveaumdp', $parentUser->fresh()->password))->toBeTrue();
});
