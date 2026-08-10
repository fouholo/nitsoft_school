<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Invoice;
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

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $studentSmallDebt->id,
        'amount_due' => 1000,
        'amount_paid' => 900,
        'due_date' => now()->subDay(),
    ]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $studentBigDebt->id,
        'amount_due' => 1000,
        'amount_paid' => 100,
        'due_date' => now()->subDay(),
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

    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $studentA->id, 'classroom_id' => $classroomA->id, 'school_year_id' => $schoolYear->id, 'status' => 'active']);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $studentB->id, 'classroom_id' => $classroomB->id, 'school_year_id' => $schoolYear->id, 'status' => 'active']);

    Invoice::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'student_id' => $studentA->id, 'due_date' => now()->subDay()]);
    Invoice::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'student_id' => $studentB->id, 'due_date' => now()->subDay()]);

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

test('un éducateur ne voit que les montants issus de ses propres factures', function () {
    $establishment = Establishment::factory()->create();
    $educator = createUserWithRole($establishment, 'educateur');
    $otherEducator = createUserWithRole($establishment, 'educateur');
    actingInEstablishment($establishment);
    test()->actingAs($educator);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'amount_due' => 100,
        'amount_paid' => 0,
        'due_date' => now()->subDay(),
        'created_by' => $educator->id,
    ]);

    Invoice::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'amount_due' => 900,
        'amount_paid' => 0,
        'due_date' => now()->subDay(),
        'created_by' => $otherEducator->id,
    ]);

    $rows = Livewire::test(Index::class)->set('school_year_id', $schoolYear->id)->viewData('rows');

    $row = $rows->firstWhere('student_id', $student->id);

    expect($row['due_so_far'])->toBe(100.0);
});
