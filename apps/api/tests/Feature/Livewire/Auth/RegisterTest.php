<?php

declare(strict_types=1);

use App\Domain\Enrollment\Models\Guardian;
use App\Livewire\Auth\Register;
use App\Models\User;
use Livewire\Livewire;

test('un parent peut s’inscrire et un profil tuteur est créé', function () {
    Livewire::test(Register::class)
        ->set('first_name', 'Awa')
        ->set('last_name', 'Traoré')
        ->set('email', 'awa.traore@example.test')
        ->set('phone', '+2250700000000')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertRedirect(route('home'));

    $user = User::where('email', 'awa.traore@example.test')->sole();

    $this->assertAuthenticatedAs($user);

    $guardian = Guardian::where('user_id', $user->id)->sole();

    expect($guardian->first_name)->toBe('Awa')
        ->and($guardian->last_name)->toBe('Traoré')
        ->and($guardian->phone)->toBe('+2250700000000');
});

test('l’inscription échoue si l’e-mail est déjà utilisé', function () {
    User::factory()->create(['email' => 'awa.traore@example.test']);

    Livewire::test(Register::class)
        ->set('first_name', 'Awa')
        ->set('last_name', 'Traoré')
        ->set('email', 'awa.traore@example.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors('email');

    $this->assertGuest();
});
