<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Payment;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Dashboard\TrendWidget;
use Livewire\Livewire;

test('calcule la tendance entre le mois courant et le mois précédent', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $directeur->id, 'amount' => 4000, 'paid_at' => now()]);
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $directeur->id, 'amount' => 2000, 'paid_at' => now()->subMonthNoOverflow()]);

    Livewire::test(TrendWidget::class)
        ->assertViewHas('currentCollected', 4000.0)
        ->assertViewHas('previousCollected', 2000.0)
        ->assertViewHas('deltaPercent', 100.0);
});

test('l’absence d’encaissement le mois précédent laisse le pourcentage indéfini plutôt qu’une division par zéro', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $directeur->id, 'amount' => 1000, 'paid_at' => now()]);

    Livewire::test(TrendWidget::class)
        ->assertViewHas('deltaPercent', null)
        ->assertViewHas('deltaAmount', 1000.0);
});
