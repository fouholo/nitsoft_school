<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Payment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use Illuminate\Support\Facades\Gate;

test('un fondateur a accès admin à un établissement de son groupe sans ligne establishment_user', function () {
    $foundation = Foundation::factory()->create();
    $establishment = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $founder = createFounder($foundation);

    actingInEstablishment($establishment);

    expect($founder->belongsToEstablishment($establishment->id))->toBeFalse()
        ->and($founder->hasAccessTo($establishment->id))->toBeTrue()
        ->and($founder->hasAdminRightsOnCurrentEstablishment())->toBeTrue()
        ->and($founder->roleFor($establishment->id))->toBe('fondateur');
});

test('un fondateur peut consulter et créer des élèves, et consulter (sans gérer) les factures de son groupe', function () {
    // Depuis le chantier "privilèges par rôle", le fondateur a une vue
    // d'ensemble des finances mais ne gère pas les factures/paiements au
    // quotidien (RolePermissions::MATRIX['billing.manage'] exclut fondateur).
    $foundation = Foundation::factory()->create();
    $establishment = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $founder = createFounder($foundation);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    actingInEstablishment($establishment);

    expect(Gate::forUser($founder)->allows('view', $student))->toBeTrue()
        ->and(Gate::forUser($founder)->allows('create', Student::class))->toBeTrue()
        ->and(Gate::forUser($founder)->allows('viewAny', Payment::class))->toBeTrue()
        ->and(Gate::forUser($founder)->allows('create', Payment::class))->toBeFalse();
});

test('un fondateur n’a aucun accès sur un établissement hors de son groupe', function () {
    $foundation = Foundation::factory()->create();
    $outsideEstablishment = Establishment::factory()->create(['foundation_id' => null]);
    $founder = createFounder($foundation);

    actingInEstablishment($outsideEstablishment);

    expect($founder->hasAccessTo($outsideEstablishment->id))->toBeFalse()
        ->and($founder->hasAdminRightsOnCurrentEstablishment())->toBeFalse()
        ->and(Gate::forUser($founder)->allows('viewAny', Student::class))->toBeFalse();
});

test('un rôle direct establishment_user est prioritaire sur le statut de fondateur', function () {
    $foundation = Foundation::factory()->create();
    $establishment = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $founder = createFounder($foundation);

    $establishment->users()->attach($founder->id, ['role' => 'enseignant', 'is_active' => true]);

    expect($founder->roleFor($establishment->id))->toBe('enseignant');
});

test('le switcher d’établissement liste les écoles accessibles via la foundation', function () {
    $foundation = Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $unrelated = Establishment::factory()->create(['foundation_id' => null]);
    $founder = createFounder($foundation);

    $accessible = $founder->accessibleEstablishments();

    expect($accessible->pluck('id'))->toContain($schoolA->id, $schoolB->id)
        ->and($accessible->pluck('id'))->not->toContain($unrelated->id);
});
