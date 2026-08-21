<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Expense;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\FinancialSummaryService;
use App\Domain\Establishments\Models\Establishment;

test('summaryByUser agrège les paiements et dépenses par utilisateur sur la période', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $caissier = createUserWithRole($establishment, 'caissier');

    Payment::factory()->create([
        'establishment_id' => $establishment->id,
        'received_by' => $caissier->id,
        'amount' => 10000,
        'paid_at' => now(),
    ]);
    Payment::factory()->create([
        'establishment_id' => $establishment->id,
        'received_by' => $caissier->id,
        'amount' => 5000,
        'paid_at' => now(),
    ]);
    Expense::factory()->create([
        'establishment_id' => $establishment->id,
        'recorded_by' => $caissier->id,
        'amount' => 2000,
        'spent_at' => now()->toDateString(),
    ]);

    $summary = (new FinancialSummaryService)->summaryByUser(now()->subDay(), now()->addDay(), $establishment->id);

    expect($summary)->toHaveCount(1);

    $row = $summary[0];
    expect($row['user_id'])->toBe($caissier->id)
        ->and($row['user_name'])->toBe($caissier->name)
        ->and($row['role'])->toBe('caissier')
        ->and($row['collected'])->toBe(15000.0)
        ->and($row['spent'])->toBe(2000.0)
        ->and($row['net'])->toBe(13000.0);
});

test('un utilisateur apparaissant seulement dans les dépenses a un encaissé de zéro', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $gestionnaire = createUserWithRole($establishment, 'gestionnaire');

    Expense::factory()->create([
        'establishment_id' => $establishment->id,
        'recorded_by' => $gestionnaire->id,
        'amount' => 3000,
        'spent_at' => now()->toDateString(),
    ]);

    $summary = (new FinancialSummaryService)->summaryByUser(now()->subDay(), now()->addDay(), $establishment->id);

    expect($summary)->toHaveCount(1)
        ->and($summary[0]['collected'])->toBe(0.0)
        ->and($summary[0]['spent'])->toBe(3000.0)
        ->and($summary[0]['net'])->toBe(-3000.0);
});

test('les mouvements hors période sont exclus', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $caissier = createUserWithRole($establishment, 'caissier');

    Payment::factory()->create([
        'establishment_id' => $establishment->id,
        'received_by' => $caissier->id,
        'paid_at' => now()->subYear(),
    ]);

    $summary = (new FinancialSummaryService)->summaryByUser(now()->subDay(), now()->addDay(), $establishment->id);

    expect($summary)->toHaveCount(0);
});

test('ownerId restreint l’agrégation aux mouvements de cet utilisateur', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $userA = createUserWithRole($establishment, 'educateur');
    $userB = createUserWithRole($establishment, 'educateur');

    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $userA->id, 'amount' => 1000]);
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $userB->id, 'amount' => 2000]);
    Expense::factory()->create(['establishment_id' => $establishment->id, 'recorded_by' => $userB->id, 'amount' => 500]);

    $summary = (new FinancialSummaryService)->summaryByUser(now()->subDay(), now()->addDay(), $establishment->id, $userA->id);

    expect($summary)->toHaveCount(1)
        ->and($summary[0]['user_id'])->toBe($userA->id)
        ->and($summary[0]['collected'])->toBe(1000.0);
});

test('groupByRole ordonne les rôles selon la hiérarchie et calcule les sous-totaux', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $caissier = createUserWithRole($establishment, 'caissier');
    $directeur = createUserWithRole($establishment, 'directeur');

    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $caissier->id, 'amount' => 1000]);
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $directeur->id, 'amount' => 4000]);

    $service = new FinancialSummaryService;
    $summary = $service->summaryByUser(now()->subDay(), now()->addDay(), $establishment->id);
    $groups = $service->groupByRole($summary);

    expect($groups)->toHaveCount(2)
        ->and($groups[0]['role'])->toBe('directeur')
        ->and($groups[0]['collected'])->toBe(4000.0)
        ->and($groups[1]['role'])->toBe('caissier')
        ->and($groups[1]['collected'])->toBe(1000.0);
});

test('groupByRole place les rôles non reconnus ou sans rattachement sous « Autre » en dernier', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $caissier = createUserWithRole($establishment, 'caissier');
    $orphan = \App\Models\User::factory()->create();

    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $caissier->id, 'amount' => 1000]);
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $orphan->id, 'amount' => 500]);

    $service = new FinancialSummaryService;
    $summary = $service->summaryByUser(now()->subDay(), now()->addDay(), $establishment->id);
    $groups = $service->groupByRole($summary);

    $lastGroup = $groups[count($groups) - 1];

    expect($groups)->toHaveCount(2)
        ->and($lastGroup['role'])->toBeNull()
        ->and($lastGroup['roleLabel'])->toBe('Autre')
        ->and($lastGroup['collected'])->toBe(500.0);
});

test('totalsForEstablishments agrège les montants sans ventilation', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $caissier = createUserWithRole($establishment, 'caissier');

    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $caissier->id, 'amount' => 10000, 'paid_at' => now()]);
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $caissier->id, 'amount' => 5000, 'paid_at' => now()]);
    Expense::factory()->create(['establishment_id' => $establishment->id, 'recorded_by' => $caissier->id, 'amount' => 2000, 'spent_at' => now()->toDateString()]);

    $totals = (new FinancialSummaryService)->totalsForEstablishments(now()->subDay(), now()->addDay(), [$establishment->id]);

    expect($totals)->toBe(['collected' => 15000.0, 'spent' => 2000.0, 'net' => 13000.0]);
});

test('totalsForEstablishments renvoie des zéros pour une liste d’établissements vide', function () {
    $totals = (new FinancialSummaryService)->totalsForEstablishments(now()->subDay(), now()->addDay(), []);

    expect($totals)->toBe(['collected' => 0.0, 'spent' => 0.0, 'net' => 0.0]);
});

test('totalsForEstablishments additionne plusieurs établissements sans les distinguer', function () {
    $foundation = \App\Domain\Establishments\Models\Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id]);

    actingInEstablishment($schoolA);
    $directeurA = createUserWithRole($schoolA, 'directeur');
    Payment::factory()->create(['establishment_id' => $schoolA->id, 'received_by' => $directeurA->id, 'amount' => 1000]);

    actingInEstablishment($schoolB);
    $directeurB = createUserWithRole($schoolB, 'directeur');
    Payment::factory()->create(['establishment_id' => $schoolB->id, 'received_by' => $directeurB->id, 'amount' => 4000]);

    $totals = (new FinancialSummaryService)->totalsForEstablishments(now()->subDay(), now()->addDay(), [$schoolA->id, $schoolB->id]);

    expect($totals['collected'])->toBe(5000.0);
});

test('summaryByEstablishments retourne un bloc par établissement avec ses propres montants', function () {
    $foundation = \App\Domain\Establishments\Models\Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id, 'name' => 'École A']);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id, 'name' => 'École B']);

    actingInEstablishment($schoolA);
    $directeurA = createUserWithRole($schoolA, 'directeur');
    Payment::factory()->create(['establishment_id' => $schoolA->id, 'received_by' => $directeurA->id, 'amount' => 1000]);

    actingInEstablishment($schoolB);
    $directeurB = createUserWithRole($schoolB, 'directeur');
    Payment::factory()->create(['establishment_id' => $schoolB->id, 'received_by' => $directeurB->id, 'amount' => 4000]);

    $result = (new FinancialSummaryService)->summaryByEstablishments(now()->subDay(), now()->addDay(), [$schoolA->id, $schoolB->id]);

    expect($result)->toHaveCount(2)
        ->and($result[0]['establishment_id'])->toBe($schoolA->id)
        ->and($result[0]['establishmentName'])->toBe('École A')
        ->and($result[0]['collected'])->toBe(1000.0)
        ->and($result[1]['establishment_id'])->toBe($schoolB->id)
        ->and($result[1]['establishmentName'])->toBe('École B')
        ->and($result[1]['collected'])->toBe(4000.0);
});

test('summaryByEstablishments ne fusionne pas un même utilisateur actif dans deux écoles du groupe', function () {
    $foundation = \App\Domain\Establishments\Models\Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id]);

    $founder = createFounder($foundation);

    actingInEstablishment($schoolA);
    Payment::factory()->create(['establishment_id' => $schoolA->id, 'received_by' => $founder->id, 'amount' => 1000]);

    actingInEstablishment($schoolB);
    Payment::factory()->create(['establishment_id' => $schoolB->id, 'received_by' => $founder->id, 'amount' => 2500]);

    $result = (new FinancialSummaryService)->summaryByEstablishments(now()->subDay(), now()->addDay(), [$schoolA->id, $schoolB->id]);

    expect($result[0]['groups'][0]['rows'][0]['collected'])->toBe(1000.0)
        ->and($result[1]['groups'][0]['rows'][0]['collected'])->toBe(2500.0);
});

test('summaryByEstablishments respecte l’ordre des établissements passés et calcule le total de chaque bloc', function () {
    $foundation = \App\Domain\Establishments\Models\Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id]);

    actingInEstablishment($schoolA);
    $directeurA = createUserWithRole($schoolA, 'directeur');
    Payment::factory()->create(['establishment_id' => $schoolA->id, 'received_by' => $directeurA->id, 'amount' => 1000]);
    Expense::factory()->create(['establishment_id' => $schoolA->id, 'recorded_by' => $directeurA->id, 'amount' => 200]);

    $result = (new FinancialSummaryService)->summaryByEstablishments(now()->subDay(), now()->addDay(), [$schoolB->id, $schoolA->id]);

    expect($result[0]['establishment_id'])->toBe($schoolB->id)
        ->and($result[1]['establishment_id'])->toBe($schoolA->id)
        ->and($result[1]['collected'])->toBe(1000.0)
        ->and($result[1]['spent'])->toBe(200.0)
        ->and($result[1]['net'])->toBe(800.0);
});
