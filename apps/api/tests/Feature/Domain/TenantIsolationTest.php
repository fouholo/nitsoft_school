<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Notifications\Models\SmsTemplate;

/**
 * Isolation multi-tenant (défense en profondeur du Global Scope) — voir plan
 * d'architecture, section 2.1 et 9. Deux établissements factices, on vérifie
 * qu'aucune requête sur un modèle tenant-aware ne fuit d'un établissement
 * à l'autre, quel que soit le domaine. `Guardian` n'est plus tenant-aware
 * depuis l'auto-inscription des parents (un parent peut avoir des enfants
 * dans des établissements différents) — voir
 * docs/superpowers/specs/2026-08-06-parents-autoinscription-design.md.
 * `Subject` n'est plus tenant-aware depuis le catalogue global de matières
 * géré par le SaaS admin — retiré de ce dataset.
 */
dataset('tenant_aware_models', [
    'students' => [Student::class],
    'sms_templates' => [SmsTemplate::class],
    'school_years' => [SchoolYear::class],
]);

test('un modèle tenant-aware ne retourne que les données de l’établissement courant', function (string $modelClass) {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();

    $modelClass::factory()->count(3)->create(['establishment_id' => $establishmentA->id]);
    $recordsB = $modelClass::factory()->count(2)->create(['establishment_id' => $establishmentB->id]);

    app()->instance('currentEstablishmentId', $establishmentA->id);

    expect($modelClass::count())->toBe(3)
        ->and($modelClass::pluck('establishment_id')->unique()->all())->toBe([$establishmentA->id])
        ->and($modelClass::find($recordsB->first()->id))->toBeNull();
})->with('tenant_aware_models');

test('withoutTenant() donne un accès explicite cross-tenant', function (string $modelClass) {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();

    $modelClass::factory()->count(3)->create(['establishment_id' => $establishmentA->id]);
    $modelClass::factory()->count(2)->create(['establishment_id' => $establishmentB->id]);

    app()->instance('currentEstablishmentId', $establishmentA->id);

    expect($modelClass::withoutTenant()->count())->toBe(5);
})->with('tenant_aware_models');

test('establishment_id est auto-assigné à la création depuis le tenant courant', function () {
    $establishment = Establishment::factory()->create();

    app()->instance('currentEstablishmentId', $establishment->id);

    $student = Student::create([
        'first_name' => 'Awa',
        'last_name' => 'Traoré',
        'student_number' => 'MAT-0001',
    ]);

    expect($student->establishment_id)->toBe($establishment->id);
});
