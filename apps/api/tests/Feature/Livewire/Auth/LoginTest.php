<?php

declare(strict_types=1);

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

test('un utilisateur peut se connecter avec des identifiants valides', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-password')]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'secret-password')
        ->call('login')
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('la connexion échoue avec un mauvais mot de passe', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-password')]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});
