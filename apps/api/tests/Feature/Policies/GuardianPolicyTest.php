<?php

declare(strict_types=1);

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;

test('caissier est exclu de la vue des tuteurs, éducateur y a accès', function () {
    $establishment = Establishment::factory()->create();
    $cashier = createUserWithRole($establishment, 'caissier');
    $educator = createUserWithRole($establishment, 'educateur');

    actingInEstablishment($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $guardian = Guardian::factory()->create();
    $student->guardians()->attach($guardian->id, [
        'establishment_id' => $establishment->id,
        'status' => GuardianLinkStatus::Approved,
    ]);

    expect($cashier->can('viewAny', Guardian::class))->toBeFalse()
        ->and($cashier->can('view', $guardian))->toBeFalse()
        ->and($educator->can('viewAny', Guardian::class))->toBeTrue()
        ->and($educator->can('view', $guardian))->toBeTrue();
});

test('enseignant et parent gardent leur accès de vue inchangé (non-régression)', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    $parent = createUserWithRole($establishment, 'parent');

    actingInEstablishment($establishment);

    expect($teacher->can('viewAny', Guardian::class))->toBeTrue()
        ->and($parent->can('viewAny', Guardian::class))->toBeTrue();
});

test('la création/modification/suppression d’une fiche tuteur reste réservée à fondateur/directeur/gestionnaire', function () {
    $establishment = Establishment::factory()->create();
    $educator = createUserWithRole($establishment, 'educateur');
    $directeur = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);

    expect($educator->can('create', Guardian::class))->toBeFalse()
        ->and($directeur->can('create', Guardian::class))->toBeTrue();
});
