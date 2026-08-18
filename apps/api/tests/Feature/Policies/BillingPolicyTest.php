<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Expense;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Billing\Models\Payment;
use App\Domain\Establishments\Models\Establishment;

dataset('billing_models', [
    'installments' => [Installment::class],
    'level_fees' => [LevelFee::class],
    'discounts' => [Discount::class],
]);

test('admin et caissier peuvent gérer la facturation, l’enseignant non', function (string $modelClass) {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');
    $accountant = createUserWithRole($establishment, 'caissier');
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

test('seul un admin peut supprimer un paiement, pas le caissier', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');
    $accountant = createUserWithRole($establishment, 'caissier');

    actingInEstablishment($establishment);

    $payment = Payment::factory()->create(['establishment_id' => $establishment->id]);

    expect($accountant->can('create', Payment::class))->toBeTrue()
        ->and($accountant->can('delete', $payment))->toBeFalse()
        ->and($admin->can('delete', $payment))->toBeTrue();
});

test('fondateur et gestionnaire voient les factures/paiements mais ne les gèrent pas au quotidien', function () {
    $establishment = Establishment::factory()->create();
    $manager = createUserWithRole($establishment, 'gestionnaire');
    $founder = createUserWithRole($establishment, 'fondateur');

    actingInEstablishment($establishment);

    expect($manager->can('viewAny', Payment::class))->toBeTrue()
        ->and($manager->can('create', Payment::class))->toBeFalse()
        ->and($manager->can('create', Installment::class))->toBeTrue()
        ->and($manager->can('create', LevelFee::class))->toBeTrue()
        ->and($founder->can('viewAny', Payment::class))->toBeTrue()
        ->and($founder->can('create', Payment::class))->toBeFalse()
        ->and($founder->can('create', Installment::class))->toBeTrue()
        ->and($founder->can('create', LevelFee::class))->toBeTrue();
});

test('un éducateur ne voit que les paiements/dépenses qu’il a lui-même saisis', function () {
    $establishment = Establishment::factory()->create();
    $educator = createUserWithRole($establishment, 'educateur');
    $otherEducator = createUserWithRole($establishment, 'educateur');

    actingInEstablishment($establishment);

    $ownPayment = Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $educator->id]);
    $otherPayment = Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $otherEducator->id]);

    $ownExpense = Expense::factory()->create(['establishment_id' => $establishment->id, 'recorded_by' => $educator->id]);
    $otherExpense = Expense::factory()->create(['establishment_id' => $establishment->id, 'recorded_by' => $otherEducator->id]);

    expect($educator->can('view', $ownPayment))->toBeTrue()
        ->and($educator->can('view', $otherPayment))->toBeFalse()
        ->and($educator->can('view', $ownExpense))->toBeTrue()
        ->and($educator->can('view', $otherExpense))->toBeFalse();
});

test('un éducateur peut saisir des dépenses mais ne peut pas les supprimer', function () {
    $establishment = Establishment::factory()->create();
    $educator = createUserWithRole($establishment, 'educateur');

    actingInEstablishment($establishment);

    $expense = Expense::factory()->create(['establishment_id' => $establishment->id, 'recorded_by' => $educator->id]);

    expect($educator->can('create', Expense::class))->toBeTrue()
        ->and($educator->can('delete', $expense))->toBeFalse();
});
