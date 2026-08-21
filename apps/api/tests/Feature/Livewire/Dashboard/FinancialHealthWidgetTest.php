<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Expense;
use App\Domain\Billing\Models\Payment;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Livewire\Dashboard\FinancialHealthWidget;
use Livewire\Livewire;

test('affiche les totaux de l’établissement courant par défaut', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    SchoolYear::factory()->create(['is_current' => true, 'starts_on' => now()->subMonth(), 'ends_on' => now()->addMonth()]);
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $directeur->id, 'amount' => 10000, 'paid_at' => now()]);
    Expense::factory()->create(['establishment_id' => $establishment->id, 'recorded_by' => $directeur->id, 'amount' => 3000, 'spent_at' => now()->toDateString()]);

    Livewire::test(FinancialHealthWidget::class)
        ->assertViewHas('totals', ['collected' => 10000.0, 'spent' => 3000.0, 'net' => 7000.0]);
});

test('un fondateur multi-écoles agrège les totaux du groupe entier par défaut', function () {
    $foundation = Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $founder = createFounder($foundation);
    actingInEstablishment($schoolA);
    test()->actingAs($founder);

    SchoolYear::factory()->create(['is_current' => true, 'starts_on' => now()->subMonth(), 'ends_on' => now()->addMonth()]);
    Payment::factory()->create(['establishment_id' => $schoolA->id, 'received_by' => $founder->id, 'amount' => 1000, 'paid_at' => now()]);
    Payment::factory()->create(['establishment_id' => $schoolB->id, 'received_by' => $founder->id, 'amount' => 2000, 'paid_at' => now()]);

    Livewire::test(FinancialHealthWidget::class)
        ->assertViewHas('totals', fn (array $totals) => $totals['collected'] === 3000.0);
});

test('recevoir establishment-filter-changed recalcule les totaux sur la sélection', function () {
    $foundation = Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $founder = createFounder($foundation);
    actingInEstablishment($schoolA);
    test()->actingAs($founder);

    SchoolYear::factory()->create(['is_current' => true, 'starts_on' => now()->subMonth(), 'ends_on' => now()->addMonth()]);
    Payment::factory()->create(['establishment_id' => $schoolA->id, 'received_by' => $founder->id, 'amount' => 1000, 'paid_at' => now()]);
    Payment::factory()->create(['establishment_id' => $schoolB->id, 'received_by' => $founder->id, 'amount' => 2000, 'paid_at' => now()]);

    Livewire::test(FinancialHealthWidget::class)
        ->call('onEstablishmentFilterChanged', [$schoolA->id])
        ->assertViewHas('totals', fn (array $totals) => $totals['collected'] === 1000.0);
});

test('un establishment_id hors du groupe du fondateur injecté directement est silencieusement ignoré', function () {
    $foundation = Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $outsider = Establishment::factory()->create();
    $founder = createFounder($foundation);
    actingInEstablishment($schoolA);
    test()->actingAs($founder);

    SchoolYear::factory()->create(['is_current' => true, 'starts_on' => now()->subMonth(), 'ends_on' => now()->addMonth()]);
    Payment::factory()->create(['establishment_id' => $outsider->id, 'amount' => 50000, 'paid_at' => now()]);

    Livewire::test(FinancialHealthWidget::class)
        ->call('onEstablishmentFilterChanged', [$outsider->id])
        ->assertViewHas('totals', fn (array $totals) => $totals['collected'] === 0.0);
});

test('un directeur (non fondateur) injectant establishment-filter-changed n’a aucun effet', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    $otherEstablishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    SchoolYear::factory()->create(['is_current' => true, 'starts_on' => now()->subMonth(), 'ends_on' => now()->addMonth()]);
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $directeur->id, 'amount' => 1000, 'paid_at' => now()]);
    Payment::factory()->create(['establishment_id' => $otherEstablishment->id, 'amount' => 99999, 'paid_at' => now()]);

    Livewire::test(FinancialHealthWidget::class)
        ->call('onEstablishmentFilterChanged', [$otherEstablishment->id])
        ->assertViewHas('totals', fn (array $totals) => $totals['collected'] === 1000.0);
});
