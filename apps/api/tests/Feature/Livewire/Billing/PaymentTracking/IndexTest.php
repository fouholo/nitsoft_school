<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Payment;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Billing\PaymentTracking\Index;
use Livewire\Livewire;

test('un directeur voit la liste triée par retard décroissant', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $studentSmallDebt = Student::factory()->create(['establishment_id' => $establishment->id]);
    $studentBigDebt = Student::factory()->create(['establishment_id' => $establishment->id]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $studentSmallDebt->id,
        'registration_amount' => 1000,
        'total_paid' => 900,
    ]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $studentBigDebt->id,
        'registration_amount' => 1000,
        'total_paid' => 100,
    ]);

    $rows = Livewire::test(Index::class)->set('school_year_id', $schoolYear->id)->viewData('rows');

    expect($rows->pluck('student_id')->all())->toBe([$studentBigDebt->id, $studentSmallDebt->id]);
});

test('le filtre par niveau exclut un élève d’un autre niveau', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $levelA = Level::factory()->create();
    $levelB = Level::factory()->create();
    $classroomA = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $levelA->id]);
    $classroomB = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $levelB->id]);

    $studentA = Student::factory()->create(['establishment_id' => $establishment->id]);
    $studentB = Student::factory()->create(['establishment_id' => $establishment->id]);

    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $studentA->id, 'classroom_id' => $classroomA->id, 'school_year_id' => $schoolYear->id, 'status' => 'active', 'registration_amount' => 500]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $studentB->id, 'classroom_id' => $classroomB->id, 'school_year_id' => $schoolYear->id, 'status' => 'active', 'registration_amount' => 500]);

    $rows = Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->set('levelFilter', $levelA->id)
        ->viewData('rows');

    expect($rows->pluck('student_id')->all())->toBe([$studentA->id]);
});

test('un enseignant n’a pas accès à l’écran de suivi des paiements', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);
    test()->actingAs($teacher);

    Livewire::test(Index::class)->assertForbidden();
});

test('un éducateur ne voit que les inscriptions sur lesquelles il a personnellement encaissé un paiement', function () {
    $establishment = Establishment::factory()->create();
    $educator = createUserWithRole($establishment, 'educateur');
    $otherEducator = createUserWithRole($establishment, 'educateur');
    actingInEstablishment($establishment);
    test()->actingAs($educator);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $studentOwn = Student::factory()->create(['establishment_id' => $establishment->id]);
    $studentOther = Student::factory()->create(['establishment_id' => $establishment->id]);

    $enrollmentOwn = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $studentOwn->id,
        'registration_amount' => 100,
    ]);
    $enrollmentOther = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $studentOther->id,
        'registration_amount' => 900,
    ]);

    Payment::factory()->create([
        'establishment_id' => $establishment->id,
        'enrollment_id' => $enrollmentOwn->id,
        'student_id' => $studentOwn->id,
        'received_by' => $educator->id,
    ]);
    Payment::factory()->create([
        'establishment_id' => $establishment->id,
        'enrollment_id' => $enrollmentOther->id,
        'student_id' => $studentOther->id,
        'received_by' => $otherEducator->id,
    ]);

    $rows = Livewire::test(Index::class)->set('school_year_id', $schoolYear->id)->viewData('rows');

    expect($rows->pluck('student_id')->all())->toBe([$studentOwn->id]);
});
