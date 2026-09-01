<?php

declare(strict_types=1);

use App\Models\User;

test('fullName compose prénom et nom quand le prénom est renseigné', function () {
    $user = User::factory()->make(['name' => 'Dupont', 'first_name' => 'Jean']);

    expect($user->fullName())->toBe('Jean Dupont');
});

test('fullName retombe sur name seul quand le prénom est vide', function () {
    $user = User::factory()->make(['name' => 'Jean Dupont', 'first_name' => null]);

    expect($user->fullName())->toBe('Jean Dupont');
});

test('fullName retombe sur name seul quand le prénom est une chaîne vide', function () {
    $user = User::factory()->make(['name' => 'Jean Dupont', 'first_name' => '']);

    expect($user->fullName())->toBe('Jean Dupont');
});
