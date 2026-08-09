<?php

declare(strict_types=1);

use App\Domain\Billing\Models\FeeSchedule;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Establishments\Models\Establishment;

dataset('billing_models', [
    'fee_schedules' => [FeeSchedule::class],
    'invoices' => [Invoice::class],
]);

test('admin et comptable peuvent gérer la facturation, l’enseignant non', function (string $modelClass) {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');
    $accountant = createUserWithRole($establishment, 'comptable');
    $teacher = createUserWithRole($establishment, 'enseignant');

    actingInEstablishment($establishment);

    $record = $modelClass::factory()->create(['establishment_id' => $establishment->id]);

    expect($admin->can('create', $modelClass))->toBeTrue()
        ->and($admin->can('update', $record))->toBeTrue()
        ->and($accountant->can('create', $modelClass))->toBeTrue()
        ->and($accountant->can('update', $record))->toBeTrue()
        ->and($teacher->can('viewAny', $modelClass))->toBeFalse()
        ->and($teacher->can('create', $modelClass))->toBeFalse();
})->with('billing_models');

test('seul un admin peut supprimer un paiement, pas le comptable', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');
    $accountant = createUserWithRole($establishment, 'comptable');

    actingInEstablishment($establishment);

    $payment = Payment::factory()->create(['establishment_id' => $establishment->id]);

    expect($accountant->can('create', Payment::class))->toBeTrue()
        ->and($accountant->can('delete', $payment))->toBeFalse()
        ->and($admin->can('delete', $payment))->toBeTrue();
});
