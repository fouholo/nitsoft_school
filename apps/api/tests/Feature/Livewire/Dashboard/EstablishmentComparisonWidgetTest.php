<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Payment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Livewire\Dashboard\EstablishmentComparisonWidget;
use Livewire\Livewire;

test('une carte par école, avec ses propres chiffres, sans fusion entre écoles', function () {
    $foundation = Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id, 'name' => 'École A']);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id, 'name' => 'École B']);
    $founder = createFounder($foundation);
    actingInEstablishment($schoolA);
    test()->actingAs($founder);

    SchoolYear::factory()->create(['is_current' => true, 'starts_on' => now()->subMonth(), 'ends_on' => now()->addMonth()]);
    Student::factory()->create(['establishment_id' => $schoolA->id, 'is_active' => true]);
    Payment::factory()->create(['establishment_id' => $schoolA->id, 'received_by' => $founder->id, 'amount' => 1000, 'paid_at' => now()]);
    Payment::factory()->create(['establishment_id' => $schoolB->id, 'received_by' => $founder->id, 'amount' => 4000, 'paid_at' => now()]);

    $component = Livewire::test(EstablishmentComparisonWidget::class);
    $cards = $component->viewData('cards');

    expect($cards)->toHaveCount(2);

    $cardA = collect($cards)->firstWhere('establishment_id', $schoolA->id);
    $cardB = collect($cards)->firstWhere('establishment_id', $schoolB->id);

    expect($cardA['studentsCount'])->toBe(1)
        ->and($cardA['collected'])->toBe(1000.0)
        ->and($cardB['studentsCount'])->toBe(0)
        ->and($cardB['collected'])->toBe(4000.0);
});

test('une sélection partielle ne montre que la carte de l’école cochée', function () {
    $foundation = Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $founder = createFounder($foundation);
    actingInEstablishment($schoolA);
    test()->actingAs($founder);

    $component = Livewire::test(EstablishmentComparisonWidget::class)
        ->call('onEstablishmentFilterChanged', [$schoolA->id]);

    expect($component->viewData('cards'))->toHaveCount(1);
});
