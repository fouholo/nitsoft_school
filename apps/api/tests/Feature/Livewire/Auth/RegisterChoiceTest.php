<?php

declare(strict_types=1);

use App\Livewire\Auth\Login;
use App\Livewire\Auth\RegisterChoice;
use App\Livewire\Staff\Register as StaffRegister;
use App\Models\User;
use Livewire\Livewire;

test('la page de choix propose les parcours fondateur, personnel de direction et parent', function () {
    Livewire::test(RegisterChoice::class)
        ->assertSee(__('Fondateur'))
        ->assertSee(__('Personnel de direction'))
        ->assertSee(__("Parent d'élève"))
        ->assertSee(route('register.school'))
        ->assertSee(route('staff.register', ['role' => 'fondateur']), escape: false)
        ->assertSee(route('staff.register'))
        ->assertSee(route('register.guardian'));
});

test('la page de connexion mène à la page de choix', function () {
    Livewire::test(Login::class)
        ->assertSee(__('Pas encore de compte ?'))
        ->assertSee(route('register'));
});

test('les pages d’inscription sont réservées aux visiteurs non connectés', function (string $route) {
    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertRedirect();
})->with(['register', 'register.school', 'register.guardian']);

test('les pages d’inscription s’affichent pour un visiteur', function (string $route) {
    $this->get(route($route))->assertOk();
})->with(['register', 'register.school', 'register.guardian']);

test('le paramètre role présélectionne le rôle sur l’inscription du personnel', function () {
    Livewire::withQueryParams(['role' => 'fondateur'])
        ->test(StaffRegister::class)
        ->assertSet('role', 'fondateur');
});
