<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Billing\Models\ArabicLevelFee;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Enrollment\Enums\Track;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Billing\Discounts\Index;
use App\Livewire\Students\Show;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * Réductions sur les frais d'inscription — voir
 * docs/superpowers/specs/2026-10-31-reductions-droits-inscription-design.md.
 */

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    actingInEstablishment($this->establishment);
    $this->actingAs(createUserWithRole($this->establishment, 'directeur'));

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->classroom = Classroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $this->student = Student::factory()->create(['establishment_id' => $this->establishment->id]);

    $this->levelFee = LevelFee::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'level_id' => $this->classroom->level_id,
        'registration_amount' => 20000,
        'registration_amount_assigned' => 8000,
    ]);

    $installment = Installment::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id, 'position' => 1]);
    $this->levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => 50000]);
});

function registrationEnrollment(array $attributes = []): Enrollment
{
    return Enrollment::factory()->create([
        'establishment_id' => test()->establishment->id,
        'student_id' => test()->student->id,
        'classroom_id' => test()->classroom->id,
        'school_year_id' => test()->schoolYear->id,
        'status' => 'active',
        'registration_amount' => 20000,
        'installment_1_amount' => 50000,
        ...$attributes,
    ]);
}

function discountForm(): Testable
{
    return Livewire::test(Index::class)
        ->set('school_year_id', test()->schoolYear->id)
        ->call('create')
        ->set('student_id', test()->student->id);
}

function editDiscountForm(): Testable
{
    return Livewire::test(Index::class)
        ->set('school_year_id', test()->schoolYear->id)
        ->call('edit', Discount::sole()->id);
}

test('une réduction en pourcentage sur les frais d’inscription seuls ne touche pas aux tranches, même ajustées', function () {
    $enrollment = registrationEnrollment(['installment_1_amount' => 45000]);

    discountForm()
        ->set('registration_type', 'percentage')
        ->set('registration_value', 25)
        ->call('save')
        ->assertHasNoErrors();

    $discount = Discount::sole();
    $enrollment->refresh();

    expect($discount->type)->toBeNull()
        ->and($discount->value)->toBeNull()
        ->and($discount->registration_type)->toBe('percentage')
        ->and((float) $enrollment->registration_amount)->toBe(15000.0)
        ->and((float) $enrollment->installment_1_amount)->toBe(45000.0);
});

test('un montant fixe supérieur aux frais d’inscription s’arrête à 0 sans se reporter sur la scolarité', function () {
    $enrollment = registrationEnrollment();

    discountForm()
        ->set('registration_type', 'fixed_amount')
        ->set('registration_value', 30000)
        ->call('save')
        ->assertHasNoErrors();

    $enrollment->refresh();

    expect((float) $enrollment->registration_amount)->toBe(0.0)
        ->and((float) $enrollment->installment_1_amount)->toBe(50000.0);
});

test('une réduction sur la scolarité seule conserve des frais d’inscription ajustés à la main', function () {
    $enrollment = registrationEnrollment(['registration_amount' => 12000]);

    discountForm()
        ->set('type', 'percentage')
        ->set('value', 10)
        ->call('save')
        ->assertHasNoErrors();

    $enrollment->refresh();

    expect((float) $enrollment->registration_amount)->toBe(12000.0)
        ->and((float) $enrollment->installment_1_amount)->toBe(45000.0);
});

test('les deux réductions s’appliquent chacune à leur partie', function () {
    $enrollment = registrationEnrollment();

    discountForm()
        ->set('type', 'fixed_amount')
        ->set('value', 5000)
        ->set('registration_type', 'percentage')
        ->set('registration_value', 100)
        ->call('save')
        ->assertHasNoErrors();

    $enrollment->refresh();

    expect((float) $enrollment->registration_amount)->toBe(0.0)
        ->and((float) $enrollment->installment_1_amount)->toBe(45000.0);
});

test('un élève affecté part du tarif affecté', function () {
    $enrollment = registrationEnrollment(['is_assigned' => true, 'registration_amount' => 8000]);

    discountForm()
        ->set('registration_type', 'percentage')
        ->set('registration_value', 50)
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $enrollment->refresh()->registration_amount)->toBe(4000.0);
});

test('une classe principale arabe part de la grille arabe', function () {
    $arabicClassroom = ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    ArabicLevelFee::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $arabicClassroom->arabic_level_id,
        'registration_amount' => 6000,
    ]);

    $enrollment = registrationEnrollment([
        'main_track' => Track::Arabe,
        'classroom_id' => null,
        'arabic_classroom_id' => $arabicClassroom->id,
        'registration_amount' => 6000,
    ]);

    discountForm()
        ->set('registration_type', 'fixed_amount')
        ->set('registration_value', 1000)
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $enrollment->refresh()->registration_amount)->toBe(5000.0);
});

test('modifier la réduction repart de la grille, et vider un bloc rétablit la grille de cette partie', function () {
    $enrollment = registrationEnrollment();

    discountForm()
        ->set('value', 10)
        ->set('registration_value', 50)
        ->call('save')
        ->assertHasNoErrors();

    editDiscountForm()
        ->assertSet('registration_value', 50.0)
        ->set('registration_value', 25)
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $enrollment->refresh()->registration_amount)->toBe(15000.0);

    editDiscountForm()
        ->set('registration_value', '')
        ->call('save')
        ->assertHasNoErrors();

    $discount = Discount::sole();
    $enrollment->refresh();

    expect($discount->registration_type)->toBeNull()
        ->and($discount->registration_value)->toBeNull()
        ->and((float) $enrollment->registration_amount)->toBe(20000.0)
        ->and((float) $enrollment->installment_1_amount)->toBe(45000.0);

    editDiscountForm()
        ->set('value', '')
        ->set('registration_value', 10)
        ->call('save')
        ->assertHasNoErrors();

    $enrollment->refresh();

    expect((float) $enrollment->registration_amount)->toBe(18000.0)
        ->and((float) $enrollment->installment_1_amount)->toBe(50000.0);
});

test('supprimer une réduction ne rétablit que les parties qu’elle concernait', function () {
    $enrollment = registrationEnrollment(['installment_1_amount' => 45000]);

    discountForm()
        ->set('registration_value', 50)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(Index::class)
        ->set('school_year_id', $this->schoolYear->id)
        ->assertSee(__("Supprimer cette réduction ? Les frais d'inscription de :name seront remis au montant standard du niveau.", ['name' => trim($this->student->last_name.' '.$this->student->first_name)]))
        ->call('delete', Discount::sole()->id);

    $enrollment->refresh();

    expect((float) $enrollment->registration_amount)->toBe(20000.0)
        ->and((float) $enrollment->installment_1_amount)->toBe(45000.0);
});

test('au moins une réduction est obligatoire et un pourcentage ne dépasse pas 100', function () {
    discountForm()
        ->call('save')
        ->assertHasErrors(['value' => 'required_without']);

    discountForm()
        ->set('registration_type', 'percentage')
        ->set('registration_value', 101)
        ->call('save')
        ->assertHasErrors('registration_value');

    expect(Discount::count())->toBe(0);
});

test('une réduction accordée avant l’inscription s’applique à sa création', function () {
    discountForm()
        ->set('value', 10)
        ->set('registration_value', 50)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(Show::class, ['student' => $this->student])
        ->call('addEnrollment')
        ->set('school_year_id', $this->schoolYear->id)
        ->set('classroom_id', $this->classroom->id)
        ->call('saveEnrollment')
        ->assertHasNoErrors();

    $enrollment = Enrollment::where('student_id', $this->student->id)->sole();

    expect((float) $enrollment->registration_amount)->toBe(10000.0)
        ->and((float) $enrollment->installment_1_amount)->toBe(45000.0);
});

test('des frais déjà payés puis réduits imputent le trop-versé à la scolarité', function () {
    $enrollment = registrationEnrollment(['total_paid' => 20000]);

    discountForm()
        ->set('registration_type', 'percentage')
        ->set('registration_value', 100)
        ->call('save')
        ->assertHasNoErrors();

    $enrollment->refresh();

    expect($enrollment->registrationAmountPaid())->toBe(0.0)
        ->and($enrollment->tuitionInstallmentsWithStatus()->first()['paid'])->toBe(20000.0);
});

test('une réduction supprimée peut être accordée de nouveau au même élève', function () {
    // Régression : la ligne supprimée en douceur bloquait la contrainte unique.
    $enrollment = registrationEnrollment();

    discountForm()->set('value', 10)->set('registration_value', 50)->set('reason', 'Ancien motif')->call('save')->assertHasNoErrors();

    Livewire::test(Index::class)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('delete', Discount::sole()->id);

    $newAuthor = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($newAuthor);

    discountForm()->set('registration_value', 25)->call('save')->assertHasNoErrors();

    $discount = Discount::sole();
    $enrollment->refresh();

    expect($discount->type)->toBeNull()
        ->and((float) $discount->registration_value)->toBe(25.0)
        ->and($discount->reason)->toBeNull()
        ->and($discount->created_by)->toBe($newAuthor->id)
        ->and((float) $enrollment->registration_amount)->toBe(15000.0)
        ->and((float) $enrollment->installment_1_amount)->toBe(50000.0);
});
