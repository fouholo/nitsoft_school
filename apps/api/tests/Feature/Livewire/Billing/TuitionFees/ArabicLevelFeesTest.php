<?php

declare(strict_types=1);

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Billing\Models\ArabicLevelFee;
use App\Domain\Billing\Models\Installment;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Billing\TuitionFees\ArabicLevelFees;
use App\Livewire\Billing\TuitionFees\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire, 'is_arabe' => true]);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->directeur);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->arabicLevel = ArabicLevel::factory()->create(['wording_fr' => 'Niveau arabe 1', 'cycle' => Cycle::Secondaire->value]);
    $this->installment = Installment::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id, 'position' => 1]);
});

test('l’écran Tarifs propose l’onglet des niveaux arabes dans une école franco-arabe', function () {
    Livewire::test(Index::class)
        ->set('school_year_id', $this->schoolYear->id)
        ->assertSee(__('Niveaux arabes'))
        ->set('levelTab', 'arabe')
        ->assertSeeLivewire(ArabicLevelFees::class);
});

test('l’onglet des niveaux arabes n’existe pas hors école franco-arabe', function () {
    $this->establishment->update(['is_arabe' => false]);

    Livewire::test(Index::class)
        ->set('school_year_id', $this->schoolYear->id)
        ->assertDontSee(__('Niveaux arabes'))
        ->set('levelTab', 'arabe')
        ->assertDontSeeLivewire(ArabicLevelFees::class);

    Livewire::test(ArabicLevelFees::class, ['schoolYearId' => $this->schoolYear->id])->assertForbidden();
});

test('un directeur configure les frais d’un niveau arabe', function () {
    Livewire::test(ArabicLevelFees::class, ['schoolYearId' => $this->schoolYear->id])
        ->assertSee('Niveau arabe 1')
        ->call('configureLevel', $this->arabicLevel->id)
        ->set('registration_amount', 15000)
        ->set("installment_amounts.{$this->installment->id}", 20000)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('justSaved', true);

    $arabicLevelFee = ArabicLevelFee::sole();

    expect((float) $arabicLevelFee->registration_amount)->toBe(15000.0)
        ->and($arabicLevelFee->arabic_level_id)->toBe($this->arabicLevel->id)
        ->and((float) $arabicLevelFee->installmentAmounts()->sole()->amount)->toBe(20000.0)
        ->and($arabicLevelFee->uid_serveur)->toMatch('/^256\d{9}$/');
});

test('vider une tranche retire son montant et re-configurer met à jour la même grille', function () {
    $component = Livewire::test(ArabicLevelFees::class, ['schoolYearId' => $this->schoolYear->id])
        ->call('configureLevel', $this->arabicLevel->id)
        ->set('registration_amount', 15000)
        ->set("installment_amounts.{$this->installment->id}", 20000)
        ->call('save');

    $component->call('configureLevel', $this->arabicLevel->id)
        ->assertSet('registration_amount', 15000.0)
        ->set('registration_amount', 18000)
        ->set("installment_amounts.{$this->installment->id}", '')
        ->call('save')
        ->assertHasNoErrors();

    $arabicLevelFee = ArabicLevelFee::sole();

    expect((float) $arabicLevelFee->registration_amount)->toBe(18000.0)
        ->and($arabicLevelFee->installmentAmounts()->count())->toBe(0);
});

test('les montants peuvent être dupliqués depuis un autre niveau arabe', function () {
    $sourceLevel = ArabicLevel::factory()->create(['cycle' => Cycle::Secondaire->value]);
    $source = ArabicLevelFee::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $sourceLevel->id,
        'registration_amount' => 12000,
    ]);
    $source->installmentAmounts()->create(['installment_id' => $this->installment->id, 'amount' => 30000]);

    Livewire::test(ArabicLevelFees::class, ['schoolYearId' => $this->schoolYear->id])
        ->call('configureLevel', $this->arabicLevel->id)
        ->set('duplicateSourceLevelId', $sourceLevel->id)
        ->assertSet('registration_amount', 12000.0)
        ->assertSet("installment_amounts.{$this->installment->id}", 30000.0);
});

test('un enseignant n’a pas accès aux frais des niveaux arabes', function () {
    $this->actingAs(createUserWithRole($this->establishment, 'enseignant'));

    Livewire::test(ArabicLevelFees::class, ['schoolYearId' => $this->schoolYear->id])->assertForbidden();

    expect(ArabicLevelFee::count())->toBe(0);
});
