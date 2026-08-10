<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Billing\TuitionFees\Index;
use Livewire\Livewire;

test('un directeur crée une tranche', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('createInstallment')
        ->set('label', 'Octobre')
        ->set('due_date', now()->addMonth()->toDateString())
        ->set('position', 1)
        ->call('saveInstallment')
        ->assertHasNoErrors();

    $installment = Installment::sole();

    expect($installment->label)->toBe('Octobre')
        ->and($installment->school_year_id)->toBe($schoolYear->id);
});

test('un directeur configure les tarifs d’un niveau en laissant une tranche vide', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $installment1 = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);
    $installment2 = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 2]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('configureLevel', $level->id)
        ->set('registration_amount', 15000)
        ->set("installment_amounts.{$installment1->id}", 10000)
        ->call('saveLevelFees')
        ->assertHasNoErrors();

    $levelFee = LevelFee::where('level_id', $level->id)->sole();

    expect((float) $levelFee->registration_amount)->toBe(15000.0)
        ->and($levelFee->installmentAmounts()->where('installment_id', $installment1->id)->exists())->toBeTrue()
        ->and($levelFee->installmentAmounts()->where('installment_id', $installment2->id)->exists())->toBeFalse();
});

test('reconfigurer un niveau retire un montant de tranche précédemment saisi', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 5000,
    ]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 8000]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('configureLevel', $level->id)
        ->set("installment_amounts.{$installment->id}", null)
        ->call('saveLevelFees')
        ->assertHasNoErrors();

    expect($levelFee->installmentAmounts()->where('installment_id', $installment->id)->exists())->toBeFalse();
});

test('un enseignant n’a aucun accès à l’écran des tarifs', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);
    test()->actingAs($teacher);

    Livewire::test(Index::class)->assertForbidden();
});
