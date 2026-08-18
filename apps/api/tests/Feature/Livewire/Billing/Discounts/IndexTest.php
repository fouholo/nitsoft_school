<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\LevelFee;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Billing\Discounts\Index;
use Livewire\Livewire;

test('un directeur accorde une réduction à un élève', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('create')
        ->set('student_id', $student->id)
        ->set('type', 'percentage')
        ->set('value', 20)
        ->set('reason', 'Fratrie')
        ->call('save')
        ->assertHasNoErrors();

    $discount = Discount::sole();

    expect($discount->student_id)->toBe($student->id)
        ->and($discount->type)->toBe('percentage')
        ->and((float) $discount->value)->toBe(20.0)
        ->and($discount->reason)->toBe('Fratrie')
        ->and($discount->created_by)->toBe($directeur->id);
});

test('accorder une nouvelle réduction au même élève/année remplace l’ancienne', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Discount::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'type' => 'percentage',
        'value' => 10,
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('create')
        ->set('student_id', $student->id)
        ->set('type', 'fixed_amount')
        ->set('value', 5000)
        ->call('save')
        ->assertHasNoErrors();

    expect(Discount::count())->toBe(1);

    $discount = Discount::sole();
    expect($discount->type)->toBe('fixed_amount')
        ->and((float) $discount->value)->toBe(5000.0);
});

test('un directeur supprime une réduction', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $discount = Discount::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('delete', $discount->id);

    expect(Discount::find($discount->id))->toBeNull();
});

test('un enseignant n’a aucun accès à l’écran des réductions', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);
    test()->actingAs($teacher);

    Livewire::test(Index::class)->assertForbidden();
});

/**
 * @return array{enrollment: Enrollment, schoolYear: SchoolYear, student: Student}
 */
function enrolledStudentWithLevelFee(Establishment $establishment, array $installmentAmounts, float $registrationAmount = 5000.0): array
{
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    $levelFee = LevelFee::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'level_id' => $classroom->level_id,
        'registration_amount' => $registrationAmount,
    ]);

    foreach ($installmentAmounts as $position => $amount) {
        $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => $position]);
        $levelFee->installmentAmounts()->create(['installment_id' => $installment->id, 'amount' => $amount]);
    }

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
        'registration_amount' => $registrationAmount,
        'installment_1_amount' => $installmentAmounts[1] ?? null,
        'installment_2_amount' => $installmentAmounts[2] ?? null,
        'installment_3_amount' => $installmentAmounts[3] ?? null,
    ]);

    return ['enrollment' => $enrollment, 'schoolYear' => $schoolYear, 'student' => $student];
}

test('une réduction en pourcentage retranche les tranches en partant de la dernière', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    ['enrollment' => $enrollment, 'schoolYear' => $schoolYear, 'student' => $student] = enrolledStudentWithLevelFee(
        $establishment,
        [1 => 1000, 2 => 2000, 3 => 3000],
    );

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('create')
        ->set('student_id', $student->id)
        ->set('type', 'percentage')
        ->set('value', 20)
        ->call('save')
        ->assertHasNoErrors();

    $enrollment->refresh();

    expect((float) $enrollment->installment_3_amount)->toBe(1800.0)
        ->and((float) $enrollment->installment_2_amount)->toBe(2000.0)
        ->and((float) $enrollment->installment_1_amount)->toBe(1000.0)
        ->and((float) $enrollment->registration_amount)->toBe(5000.0);
});

test('une réduction en montant fixe qui dépasse le total des tranches ne descend jamais sous 0 et ne touche pas l’inscription', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    ['enrollment' => $enrollment, 'schoolYear' => $schoolYear, 'student' => $student] = enrolledStudentWithLevelFee(
        $establishment,
        [1 => 1000, 2 => 2000, 3 => 3000],
    );

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('create')
        ->set('student_id', $student->id)
        ->set('type', 'fixed_amount')
        ->set('value', 10000)
        ->call('save')
        ->assertHasNoErrors();

    $enrollment->refresh();

    expect((float) $enrollment->installment_1_amount)->toBe(0.0)
        ->and((float) $enrollment->installment_2_amount)->toBe(0.0)
        ->and((float) $enrollment->installment_3_amount)->toBe(0.0)
        ->and((float) $enrollment->registration_amount)->toBe(5000.0);
});

test('modifier une réduction déjà appliquée repart des tarifs du niveau (pas de double retranchement)', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    ['enrollment' => $enrollment, 'schoolYear' => $schoolYear, 'student' => $student] = enrolledStudentWithLevelFee(
        $establishment,
        [1 => 5000],
    );

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('create')
        ->set('student_id', $student->id)
        ->set('type', 'fixed_amount')
        ->set('value', 1000)
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $enrollment->refresh()->installment_1_amount)->toBe(4000.0);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('edit', Discount::sole()->id)
        ->set('value', 3000)
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $enrollment->refresh()->installment_1_amount)->toBe(2000.0);
});

test('supprimer une réduction réinitialise les tranches aux valeurs par défaut du niveau', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    ['enrollment' => $enrollment, 'schoolYear' => $schoolYear, 'student' => $student] = enrolledStudentWithLevelFee(
        $establishment,
        [1 => 5000],
    );

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('create')
        ->set('student_id', $student->id)
        ->set('type', 'fixed_amount')
        ->set('value', 3000)
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $enrollment->refresh()->installment_1_amount)->toBe(2000.0);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('delete', Discount::sole()->id);

    expect((float) $enrollment->refresh()->installment_1_amount)->toBe(5000.0);
});
