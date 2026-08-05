<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;

/**
 * RBAC : Admin établissement peut tout faire sur ces domaines, Enseignant
 * peut seulement consulter, aucun rôle ne peut agir en dehors de son
 * établissement — voir plan d'architecture, section 2.2 et Policies.
 */
dataset('policy_models', [
    'school_years' => [SchoolYear::class],
    'classrooms' => [Classroom::class],
    'subjects' => [Subject::class],
    'students' => [Student::class],
    'enrollments' => [Enrollment::class],
]);

test('un admin peut consulter, créer, modifier et supprimer', function (string $modelClass) {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'admin');

    actingInEstablishment($establishment);

    $record = $modelClass::factory()->create(['establishment_id' => $establishment->id]);

    expect($admin->can('viewAny', $modelClass))->toBeTrue()
        ->and($admin->can('create', $modelClass))->toBeTrue()
        ->and($admin->can('view', $record))->toBeTrue()
        ->and($admin->can('update', $record))->toBeTrue()
        ->and($admin->can('delete', $record))->toBeTrue();
})->with('policy_models');

test('un enseignant peut seulement consulter', function (string $modelClass) {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'teacher');

    actingInEstablishment($establishment);

    $record = $modelClass::factory()->create(['establishment_id' => $establishment->id]);

    expect($teacher->can('viewAny', $modelClass))->toBeTrue()
        ->and($teacher->can('view', $record))->toBeTrue()
        ->and($teacher->can('create', $modelClass))->toBeFalse()
        ->and($teacher->can('update', $record))->toBeFalse()
        ->and($teacher->can('delete', $record))->toBeFalse();
})->with('policy_models');

test('un admin d’un autre établissement ne peut ni voir ni modifier', function (string $modelClass) {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();
    $adminB = createUserWithRole($establishmentB, 'admin');

    actingInEstablishment($establishmentA);
    $recordA = $modelClass::factory()->create(['establishment_id' => $establishmentA->id]);

    actingInEstablishment($establishmentB);

    expect($adminB->can('view', $recordA))->toBeFalse()
        ->and($adminB->can('update', $recordA))->toBeFalse()
        ->and($adminB->can('delete', $recordA))->toBeFalse();
})->with('policy_models');

test('le Super Admin SaaS contourne toutes les Policies', function (string $modelClass) {
    $establishment = Establishment::factory()->create();
    $otherEstablishment = Establishment::factory()->create();
    $superAdmin = createUserWithRole($establishment, 'super_admin');

    actingInEstablishment($otherEstablishment);
    $record = $modelClass::factory()->create(['establishment_id' => $otherEstablishment->id]);

    expect($superAdmin->can('view', $record))->toBeTrue()
        ->and($superAdmin->can('update', $record))->toBeTrue()
        ->and($superAdmin->can('delete', $record))->toBeTrue();
})->with('policy_models');
