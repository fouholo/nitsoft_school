<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Billing\TuitionFees\Index;
use Livewire\Livewire;

test('la rubrique "Tarifs par niveau" n’affiche que les niveaux du cycle de l’établissement', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $primaireLevel = Level::factory()->primaire()->create();
    $secondaireLevel = Level::factory()->create();

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->assertSee($primaireLevel->level_wording)
        ->assertDontSee($secondaireLevel->level_wording);
});

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

test('un directeur peut recréer une tranche à la même position après suppression de l’ancienne', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('deleteInstallment', $installment->id);

    expect(Installment::withTrashed()->find($installment->id)->trashed())->toBeTrue();

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('createInstallment')
        ->set('label', 'Octobre (bis)')
        ->set('due_date', now()->addMonth()->toDateString())
        ->set('position', 1)
        ->call('saveInstallment')
        ->assertHasNoErrors();

    $newInstallment = Installment::where('label', 'Octobre (bis)')->sole();

    expect($newInstallment->position)->toBe(1);
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

test('un directeur peut reconfigurer un niveau dont le tarif avait été supprimé (soft delete)', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();

    $trashedLevelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 5000,
    ]);
    $trashedLevelFee->delete();

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('configureLevel', $level->id)
        ->set('registration_amount', 20000)
        ->call('saveLevelFees')
        ->assertHasNoErrors();

    $levelFee = LevelFee::where('level_id', $level->id)->sole();

    expect($levelFee->id)->toBe($trashedLevelFee->id)
        ->and($levelFee->trashed())->toBeFalse()
        ->and((float) $levelFee->registration_amount)->toBe(20000.0);
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

test('le champ frais d’inscription affecté n’apparaît que pour un niveau secondaire', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $secondaireLevel = Level::factory()->create();

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('configureLevel', $secondaireLevel->id)
        ->assertSee('Frais d\'inscription (affecté)', false);
});

test('le champ frais d’inscription affecté n’apparaît pas pour un niveau primaire', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $primaireLevel = Level::factory()->primaire()->create();

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('configureLevel', $primaireLevel->id)
        ->assertDontSee('Frais d\'inscription (affecté)', false);
});

test('un message confirme l’enregistrement d’une tranche puis d’un tarif de niveau', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();

    $component = Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->assertDontSee('Tranche enregistrée.')
        ->call('createInstallment')
        ->set('label', 'Octobre')
        ->set('due_date', now()->addMonth()->toDateString())
        ->set('position', 1)
        ->call('saveInstallment')
        ->assertHasNoErrors()
        ->assertSee('Tranche enregistrée.');

    $component->assertDontSee('Tarifs enregistrés.')
        ->call('configureLevel', $level->id)
        ->set('registration_amount', 15000)
        ->call('saveLevelFees')
        ->assertHasNoErrors()
        ->assertSee('Tarifs enregistrés.');
});

test('changer d’année scolaire efface les messages de confirmation affichés', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYearA = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $schoolYearB = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYearA->id)
        ->call('createInstallment')
        ->set('label', 'Octobre')
        ->set('due_date', now()->addMonth()->toDateString())
        ->set('position', 1)
        ->call('saveInstallment')
        ->assertSee('Tranche enregistrée.')
        ->set('school_year_id', $schoolYearB->id)
        ->assertDontSee('Tranche enregistrée.');
});

test('dupliquer les montants depuis un autre niveau déjà configuré pré-remplit le formulaire', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);
    $sourceLevel = Level::factory()->create();
    $targetLevel = Level::factory()->create();

    $sourceLevelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $sourceLevel->id,
        'registration_amount' => 25000,
        'registration_amount_assigned' => 15000,
    ]);
    $sourceLevelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 50000]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('configureLevel', $targetLevel->id)
        ->assertSee($sourceLevel->level_wording)
        ->set('duplicateSourceLevelId', $sourceLevel->id)
        ->assertSet('registration_amount', 25000.0)
        ->assertSet('registration_amount_assigned', 15000.0)
        ->assertSet("installment_amounts.{$installment->id}", 50000.0)
        ->call('saveLevelFees')
        ->assertHasNoErrors();

    $targetLevelFee = LevelFee::where('level_id', $targetLevel->id)->sole();

    expect((float) $targetLevelFee->registration_amount)->toBe(25000.0)
        ->and((float) $targetLevelFee->registration_amount_assigned)->toBe(15000.0)
        ->and((float) $targetLevelFee->installmentAmounts()->where('installment_id', $installment->id)->sole()->amount)->toBe(50000.0);
});

test('le sélecteur de duplication n’apparaît pas quand aucun autre niveau n’est configuré', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('configureLevel', $level->id)
        ->assertDontSee('Dupliquer les montants depuis un autre niveau');
});
