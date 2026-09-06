<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SubjectCoefficient;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;

dataset('subject_coefficients_allowed_roles', ['fondateur', 'directeur', 'gestionnaire', 'educateur']);
dataset('subject_coefficients_denied_roles', ['caissier', 'enseignant']);

test('un rôle habilité peut voir l’écran des coefficients par matière', function (string $role) {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $user = createUserWithRole($establishment, $role);
    actingInEstablishment($establishment);

    expect($user->can('viewAny', SubjectCoefficient::class))->toBeTrue();
})->with('subject_coefficients_allowed_roles');

test('un rôle non habilité ne voit pas l’écran des coefficients par matière', function (string $role) {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $user = createUserWithRole($establishment, $role);
    actingInEstablishment($establishment);

    expect($user->can('viewAny', SubjectCoefficient::class))->toBeFalse();
})->with('subject_coefficients_denied_roles');

test('un directeur d’un établissement préscolaire/primaire ne voit pas l’écran', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $user = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    expect($user->can('viewAny', SubjectCoefficient::class))->toBeFalse();
});

test('un directeur d’un autre établissement ne peut ni voir ni gérer les coefficients de celui-ci', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $otherEstablishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $otherDirecteur = createUserWithRole($otherEstablishment, 'directeur');

    actingInEstablishment($establishment);

    expect($otherDirecteur->can('viewAny', SubjectCoefficient::class))->toBeFalse();
});
