<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\SchoolRegistration;
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

test('un fondateur dont la demande est en attente est informé, par e-mail comme par pseudo', function (string $identifiant) {
    SchoolRegistration::create([
        'name' => 'Yao',
        'first_name' => 'Kouadio',
        'email' => 'kouadio.yao@example.test',
        'pseudo' => 'kyao',
        'password' => 'password123',
        'establishment_name' => 'École Les Palmiers',
        'establishment_type' => EstablishmentType::Secondaire->value,
    ]);

    Livewire::test(Login::class)
        ->set('identifiant', $identifiant)
        ->set('password', 'password123')
        ->call('login')
        ->assertHasErrors('identifiant')
        ->assertSee(__("Votre inscription est en attente de validation par l'équipe Nitsoft."));

    $this->assertGuest();
})->with(['kouadio.yao@example.test', 'KYAO']);

test('une demande en attente avec un mauvais mot de passe donne l’erreur habituelle', function () {
    SchoolRegistration::create([
        'name' => 'Yao',
        'first_name' => 'Kouadio',
        'email' => 'kouadio.yao@example.test',
        'pseudo' => 'kyao',
        'password' => 'password123',
        'establishment_name' => 'École Les Palmiers',
        'establishment_type' => EstablishmentType::Secondaire->value,
    ]);

    Livewire::test(Login::class)
        ->set('identifiant', 'kouadio.yao@example.test')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('identifiant')
        ->assertSee(__('Ces identifiants ne correspondent à aucun compte.'))
        ->assertDontSee(__("Votre inscription est en attente de validation par l'équipe Nitsoft."));
});
