<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\SchoolRegistration;

beforeEach(function () {
    $this->registration = SchoolRegistration::create([
        'name' => 'Yao',
        'first_name' => 'Kouadio',
        'email' => 'kouadio.yao@example.test',
        'pseudo' => 'kyao',
        'password' => 'password123',
        'establishment_name' => 'École Les Palmiers',
        'establishment_type' => EstablishmentType::Secondaire->value,
    ]);
});

test('un saas admin principal ou secondaire peut consulter, valider et refuser', function (string $type) {
    $admin = createSaasAdmin($type);

    expect($admin->can('viewAny', SchoolRegistration::class))->toBeTrue()
        ->and($admin->can('approve', $this->registration))->toBeTrue()
        ->and($admin->can('reject', $this->registration))->toBeTrue();
})->with(['main', 'second']);

test('un membre d’établissement ne peut ni consulter, ni valider, ni refuser', function (string $role) {
    $user = createUserWithRole(Establishment::factory()->create(), $role);

    expect($user->can('viewAny', SchoolRegistration::class))->toBeFalse()
        ->and($user->can('approve', $this->registration))->toBeFalse()
        ->and($user->can('reject', $this->registration))->toBeFalse();
})->with(['fondateur', 'directeur']);
