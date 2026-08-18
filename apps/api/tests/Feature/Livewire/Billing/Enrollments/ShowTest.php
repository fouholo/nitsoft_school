<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Models\Payment;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Billing\Enrollments\Show;
use Livewire\Livewire;

test('un caissier consulte le détail dû/versé d’une inscription', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');
    actingInEstablishment($establishment);
    test()->actingAs($accountant);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->addMonth()]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'installment_1_amount' => 10000,
        'total_paid' => 5000,
    ]);

    Livewire::test(Show::class, ['enrollment' => $enrollment])
        ->assertSee(money(15000.0))
        ->assertSee(money(5000.0));
});

test('le détail des montants dus affiche le statut de chaque tranche', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');
    actingInEstablishment($establishment);
    test()->actingAs($accountant);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->subMonth()]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 2, 'due_date' => now()->subDay()]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 3, 'due_date' => now()->addMonth()]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 0,
        'installment_1_amount' => 5000,
        'installment_2_amount' => 5000,
        'installment_3_amount' => 5000,
        'total_paid' => 7000,
    ]);

    Livewire::test(Show::class, ['enrollment' => $enrollment])
        ->assertSeeInOrder(['Payé', 'En cours', 'Dû']);
});

test('le détail des montants dus affiche le montant versé par poste', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');
    actingInEstablishment($establishment);
    test()->actingAs($accountant);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->subMonth()]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 2000,
        'installment_1_amount' => 5000,
        'total_paid' => 6000,
    ]);

    Livewire::test(Show::class, ['enrollment' => $enrollment])
        ->assertSee(money(2000.0))
        ->assertSee(money(4000.0));
});

test('un caissier modifie les montants d’une inscription', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');
    actingInEstablishment($establishment);
    test()->actingAs($accountant);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'registration_amount' => 5000,
    ]);

    Livewire::test(Show::class, ['enrollment' => $enrollment])
        ->call('editAmounts')
        ->set('registration_amount', 6000)
        ->set('installment_amounts.1', 12000)
        ->call('saveAmounts')
        ->assertHasNoErrors();

    $enrollment->refresh();

    expect((float) $enrollment->registration_amount)->toBe(6000.0)
        ->and((float) $enrollment->installment_1_amount)->toBe(12000.0);
});

test('un caissier enregistre un paiement, le total versé de l’inscription est mis à jour', function () {
    $establishment = Establishment::factory()->create();
    $accountant = createUserWithRole($establishment, 'caissier');
    actingInEstablishment($establishment);
    test()->actingAs($accountant);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'registration_amount' => 5000,
    ]);

    Livewire::test(Show::class, ['enrollment' => $enrollment])
        ->call('addPayment')
        ->set('amount', 2000)
        ->set('method', 'cash')
        ->set('paid_at', now()->toDateString())
        ->call('savePayment')
        ->assertHasNoErrors();

    $enrollment->refresh();

    expect((float) $enrollment->total_paid)->toBe(2000.0)
        ->and(Payment::where('enrollment_id', $enrollment->id)->count())->toBe(1);
});

test('un enseignant n’a aucun accès à la fiche financière d’une inscription', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);
    test()->actingAs($teacher);

    $enrollment = Enrollment::factory()->create(['establishment_id' => $establishment->id]);

    Livewire::test(Show::class, ['enrollment' => $enrollment])->assertForbidden();
});
