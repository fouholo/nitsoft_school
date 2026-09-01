<?php

declare(strict_types=1);

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

test('un utilisateur peut se connecter avec son email', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-password')]);

    Livewire::test(Login::class)
        ->set('identifiant', $user->email)
        ->set('password', 'secret-password')
        ->call('login')
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('la connexion échoue avec un mauvais mot de passe', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-password')]);

    Livewire::test(Login::class)
        ->set('identifiant', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('identifiant');

    $this->assertGuest();
});

test('un utilisateur peut se connecter avec son pseudo', function () {
    $user = User::factory()->create(['pseudo' => 'jdupont', 'password' => bcrypt('secret-password')]);

    Livewire::test(Login::class)
        ->set('identifiant', 'jdupont')
        ->set('password', 'secret-password')
        ->call('login')
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('la connexion par pseudo est insensible à la casse', function () {
    $user = User::factory()->create(['pseudo' => 'jdupont', 'password' => bcrypt('secret-password')]);

    Livewire::test(Login::class)
        ->set('identifiant', 'JDupont')
        ->set('password', 'secret-password')
        ->call('login')
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('la connexion échoue avec un pseudo inexistant', function () {
    Livewire::test(Login::class)
        ->set('identifiant', 'inconnu')
        ->set('password', 'secret-password')
        ->call('login')
        ->assertHasErrors('identifiant');

    $this->assertGuest();
});

test('un compte sans pseudo (parent) reste connectable par email', function () {
    $guardian = User::factory()->create(['pseudo' => null, 'password' => bcrypt('secret-password')]);

    Livewire::test(Login::class)
        ->set('identifiant', $guardian->email)
        ->set('password', 'secret-password')
        ->call('login')
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($guardian);
});

test('l’écran de connexion s’affiche sans erreur dans chacune des locales prises en charge', function (string $locale) {
    app()->setLocale($locale);

    Livewire::test(Login::class)->assertOk();
})->with(['fr', 'en', 'ar']);
