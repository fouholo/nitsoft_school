<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Installment;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Models\Establishment;

test('une tranche entièrement couverte par les versements est "paid"', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->subMonth()]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 0,
        'installment_1_amount' => 5000,
        'total_paid' => 5000,
    ]);

    expect($enrollment->tuitionInstallmentsWithStatus()->first()['status'])->toBe('paid');
});

test('une tranche partiellement couverte et déjà échue est "partial_late"', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->subMonth()]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 0,
        'installment_1_amount' => 5000,
        'total_paid' => 2000,
    ]);

    expect($enrollment->tuitionInstallmentsWithStatus()->first()['status'])->toBe('partial_late');
});

test('une tranche partiellement couverte mais pas encore échue est "partial_upcoming"', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->addMonth()]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 0,
        'installment_1_amount' => 5000,
        'total_paid' => 2000,
    ]);

    expect($enrollment->tuitionInstallmentsWithStatus()->first()['status'])->toBe('partial_upcoming');
});

test('une tranche non couverte et déjà échue est "late"', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->subMonth()]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 0,
        'installment_1_amount' => 5000,
        'total_paid' => 0,
    ]);

    expect($enrollment->tuitionInstallmentsWithStatus()->first()['status'])->toBe('late');
});

test('une tranche non couverte et pas encore échue est "due"', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->addMonth()]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 0,
        'installment_1_amount' => 5000,
        'total_paid' => 0,
    ]);

    expect($enrollment->tuitionInstallmentsWithStatus()->first()['status'])->toBe('due');
});

test('les versements couvrent d’abord l’inscription avant de compter pour le statut des tranches', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->subMonth()]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'installment_1_amount' => 5000,
        'total_paid' => 5000,
    ]);

    // Les 5000 versés couvrent uniquement l'inscription, rien sur la tranche.
    expect($enrollment->tuitionInstallmentsWithStatus()->first()['status'])->toBe('late');
});

test('le montant versé par tranche est déduit de l’imputation cumulative des versements', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->subMonth()]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 2, 'due_date' => now()->addMonth()]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 2000,
        'installment_1_amount' => 5000,
        'installment_2_amount' => 3000,
        'total_paid' => 6000,
    ]);

    // 6000 versés : 2000 couvrent l'inscription, 4000 restants sur la
    // tranche 1 (5000 due, partielle), rien sur la tranche 2.
    $installments = $enrollment->tuitionInstallmentsWithStatus()->keyBy('position');

    expect($enrollment->registrationAmountPaid())->toBe(2000.0)
        ->and($installments[1]['paid'])->toBe(4000.0)
        ->and($installments[2]['paid'])->toBe(0.0);
});

test('le montant versé d’une tranche entièrement couverte est plafonné à son propre montant', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->subMonth()]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 0,
        'installment_1_amount' => 5000,
        'total_paid' => 9000,
    ]);

    expect($enrollment->tuitionInstallmentsWithStatus()->first()['paid'])->toBe(5000.0);
});

test('les tranches suivantes restent dues tant que la tranche précédente n’est pas entièrement couverte', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->subMonth()]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 2, 'due_date' => now()->addMonth()]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 0,
        'installment_1_amount' => 5000,
        'installment_2_amount' => 3000,
        'total_paid' => 6000,
    ]);

    $statuses = $enrollment->tuitionInstallmentsWithStatus()->pluck('status', 'position');

    expect($statuses[1])->toBe('paid')
        ->and($statuses[2])->toBe('partial_upcoming');
});
