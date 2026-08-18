<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Enrollment\Models\Enrollment;
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

test('un directeur génère les factures manquantes pour un niveau, inscription et tranches configurées, la tranche vide est ignorée', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $level->id]);

    $installment1 = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);
    $installment2 = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 2]);
    $installment3 = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 3]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 5000,
    ]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment1->id, 'amount' => 10000]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment2->id, 'amount' => 12000]);
    // $installment3 volontairement laissée sans montant : non due pour ce niveau.

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
        'enrolled_on' => '2026-09-15',
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id);

    $invoices = Invoice::where('student_id', $enrollment->student_id)->get();

    expect($invoices)->toHaveCount(3);

    $registrationInvoice = $invoices->firstWhere('installment_id', null);
    expect($registrationInvoice)->not->toBeNull()
        ->and((float) $registrationInvoice->amount_due)->toBe(5000.0)
        ->and($registrationInvoice->due_date->toDateString())->toBe('2026-09-15');

    $invoice1 = $invoices->firstWhere('installment_id', $installment1->id);
    expect($invoice1)->not->toBeNull()->and((float) $invoice1->amount_due)->toBe(10000.0);

    $invoice2 = $invoices->firstWhere('installment_id', $installment2->id);
    expect($invoice2)->not->toBeNull()->and((float) $invoice2->amount_due)->toBe(12000.0);

    expect($invoices->firstWhere('installment_id', $installment3->id))->toBeNull();
});

test('générer les factures une seconde fois ne crée aucun doublon', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $level->id]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 5000,
    ]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 10000]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
    ]);

    $component = Livewire::test(Index::class)->set('school_year_id', $schoolYear->id);

    $component->call('generateInvoices', $level->id);
    $countAfterFirstCall = Invoice::count();

    $component->call('generateInvoices', $level->id);

    expect(Invoice::count())->toBe($countAfterFirstCall);
});

test('modifier le tarif après génération ne change pas les factures déjà émises', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $level->id]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 5000,
    ]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 10000]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
    ]);

    $component = Livewire::test(Index::class)->set('school_year_id', $schoolYear->id);
    $component->call('generateInvoices', $level->id);

    $invoice = Invoice::where('student_id', $enrollment->student_id)->where('installment_id', $installment->id)->sole();

    $levelFee->installmentAmounts()->where('installment_id', $installment->id)->update(['amount' => 99999]);

    $component->call('generateInvoices', $level->id);

    expect((float) $invoice->refresh()->amount_due)->toBe(10000.0);
});

test('un élève inscrit dans un autre niveau n’est pas concerné par la génération', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $otherLevel = Level::factory()->create();
    $otherClassroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $otherLevel->id]);

    LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 5000,
    ]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $otherClassroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id);

    expect(Invoice::where('student_id', $enrollment->student_id)->exists())->toBeFalse();
});

test('un gestionnaire ne peut pas générer les factures', function () {
    // finance.access mais pas billing.manage (RolePermissions).
    $establishment = Establishment::factory()->create();
    $gestionnaire = createUserWithRole($establishment, 'gestionnaire');
    actingInEstablishment($establishment);
    test()->actingAs($gestionnaire);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id)
        ->assertForbidden();
});

test('une réduction en pourcentage réduit chaque tranche mais pas les frais d’inscription', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $level->id]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 5000,
    ]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 10000]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
    ]);

    Discount::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $enrollment->student_id,
        'school_year_id' => $schoolYear->id,
        'type' => 'percentage',
        'value' => 20,
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id);

    $registrationInvoice = Invoice::where('student_id', $enrollment->student_id)->whereNull('installment_id')->sole();
    $tuitionInvoice = Invoice::where('student_id', $enrollment->student_id)->where('installment_id', $installment->id)->sole();

    expect((float) $registrationInvoice->amount_due)->toBe(5000.0)
        ->and((float) $tuitionInvoice->amount_due)->toBe(8000.0);
});

test('une réduction en montant fixe est répartie au prorata entre les tranches', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $level->id]);
    $installment1 = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);
    $installment2 = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 2]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 0,
    ]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment1->id, 'amount' => 7000]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment2->id, 'amount' => 3000]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
    ]);

    Discount::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $enrollment->student_id,
        'school_year_id' => $schoolYear->id,
        'type' => 'fixed_amount',
        'value' => 3000,
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id);

    $invoice1 = Invoice::where('student_id', $enrollment->student_id)->where('installment_id', $installment1->id)->sole();
    $invoice2 = Invoice::where('student_id', $enrollment->student_id)->where('installment_id', $installment2->id)->sole();

    expect((float) $invoice1->amount_due)->toBe(4900.0)
        ->and((float) $invoice2->amount_due)->toBe(2100.0);
});

test('un élève sans réduction n’est pas affecté', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $level->id]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 0,
    ]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 10000]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id);

    $invoice = Invoice::where('student_id', $enrollment->student_id)->where('installment_id', $installment->id)->sole();

    expect((float) $invoice->amount_due)->toBe(10000.0);
});

test('un niveau secondaire facture différemment les élèves affectés et non affectés, seuls les non affectés reçoivent les tranches', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $level->id]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 5000,
        'registration_amount_assigned' => 12000,
    ]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 10000]);

    $notAssigned = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
        'is_assigned' => false,
    ]);
    $assigned = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
        'is_assigned' => true,
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id);

    $notAssignedRegistration = Invoice::where('student_id', $notAssigned->student_id)->whereNull('installment_id')->sole();
    $assignedRegistration = Invoice::where('student_id', $assigned->student_id)->whereNull('installment_id')->sole();

    expect((float) $notAssignedRegistration->amount_due)->toBe(5000.0)
        ->and((float) $assignedRegistration->amount_due)->toBe(12000.0)
        ->and(Invoice::where('student_id', $notAssigned->student_id)->where('installment_id', $installment->id)->exists())->toBeTrue()
        ->and(Invoice::where('student_id', $assigned->student_id)->where('installment_id', $installment->id)->exists())->toBeFalse();
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

test('un élève devenu affecté voit ses tranches impayées supprimées à la régénération, sans toucher à la facture d’inscription déjà émise', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $level->id]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 5000,
        'registration_amount_assigned' => 12000,
    ]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 10000]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
        'is_assigned' => false,
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id);

    $registrationInvoice = Invoice::where('student_id', $enrollment->student_id)->whereNull('installment_id')->sole();
    expect((float) $registrationInvoice->amount_due)->toBe(5000.0);

    $enrollment->update(['is_assigned' => true]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id);

    expect(Invoice::where('student_id', $enrollment->student_id)->where('installment_id', $installment->id)->exists())->toBeFalse()
        ->and((float) $registrationInvoice->refresh()->amount_due)->toBe(5000.0);
});

test('une tranche déjà partiellement payée n’est pas supprimée quand l’élève devient affecté', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $level->id]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 5000,
        'registration_amount_assigned' => 12000,
    ]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 10000]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
        'is_assigned' => false,
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id);

    $tuitionInvoice = Invoice::where('student_id', $enrollment->student_id)->where('installment_id', $installment->id)->sole();
    $tuitionInvoice->update(['amount_paid' => 2000, 'status' => 'partially_paid']);

    $enrollment->update(['is_assigned' => true]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id);

    expect(Invoice::find($tuitionInvoice->id))->not->toBeNull();
});

test('un élève devenu non affecté reçoit les tranches manquantes à la régénération, sans doublon de la facture d’inscription', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $level = Level::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $level->id]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $level->id,
        'registration_amount' => 5000,
        'registration_amount_assigned' => 12000,
    ]);
    $levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 10000]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
        'is_assigned' => true,
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id);

    expect(Invoice::where('student_id', $enrollment->student_id)->whereNull('installment_id')->count())->toBe(1)
        ->and(Invoice::where('student_id', $enrollment->student_id)->where('installment_id', $installment->id)->exists())->toBeFalse();

    $enrollment->update(['is_assigned' => false]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('generateInvoices', $level->id);

    expect(Invoice::where('student_id', $enrollment->student_id)->whereNull('installment_id')->count())->toBe(1)
        ->and(Invoice::where('student_id', $enrollment->student_id)->where('installment_id', $installment->id)->exists())->toBeTrue();
});
