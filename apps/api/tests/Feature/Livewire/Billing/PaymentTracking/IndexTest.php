<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Installment;
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

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
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

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
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

test('un directeur peut accéder directement à l’encaissement depuis le suivi des paiements', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'registration_amount' => 1000,
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->assertSee(route('billing.enrollments.show', $enrollment), false);
});

test('le filtre de statut isole les élèves en retard, à jour ou en avance', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $late = Student::factory()->create(['establishment_id' => $establishment->id]);
    $onTime = Student::factory()->create(['establishment_id' => $establishment->id]);
    $advance = Student::factory()->create(['establishment_id' => $establishment->id]);

    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'student_id' => $late->id, 'registration_amount' => 1000, 'total_paid' => 400]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'student_id' => $onTime->id, 'registration_amount' => 1000, 'total_paid' => 1000]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'student_id' => $advance->id, 'registration_amount' => 1000, 'total_paid' => 1500]);

    $component = Livewire::test(Index::class)->set('school_year_id', $schoolYear->id);

    expect($component->set('statusFilter', 'late')->viewData('rows')->pluck('student_id')->all())->toBe([$late->id])
        ->and($component->set('statusFilter', 'ontime')->viewData('rows')->pluck('student_id')->all())->toBe([$onTime->id])
        ->and($component->set('statusFilter', 'advance')->viewData('rows')->pluck('student_id')->all())->toBe([$advance->id]);

    $component->set('statusFilter', '');
    expect($component->viewData('lateCount'))->toBe(1)
        ->and((float) $component->viewData('lateTotal'))->toBe(600.0);
});

test('la recherche filtre par nom d’élève', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $kone = Student::factory()->create(['establishment_id' => $establishment->id, 'first_name' => 'Aïcha', 'last_name' => 'Koné']);
    $traore = Student::factory()->create(['establishment_id' => $establishment->id, 'first_name' => 'Moussa', 'last_name' => 'Traoré']);

    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'student_id' => $kone->id, 'registration_amount' => 500]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'student_id' => $traore->id, 'registration_amount' => 500]);

    $rows = Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->set('search', 'koné')
        ->viewData('rows');

    expect($rows->pluck('student_id')->all())->toBe([$kone->id]);
});

test('le message d’état vide distingue l’absence d’année scolaire d’une sélection sans résultat', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire::test(Index::class)
        ->set('school_year_id', null)
        ->assertSee('Sélectionnez une année scolaire')
        ->assertDontSee('Aucun élève ne correspond');

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->assertSee('Aucun élève ne correspond')
        ->assertDontSee('Sélectionnez une année scolaire');
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

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
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

test('un éducateur à portée restreinte voit une bannière explicative, un directeur non', function () {
    $establishment = Establishment::factory()->create();
    $educator = createUserWithRole($establishment, 'educateur');
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);

    test()->actingAs($educator);
    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->assertSee('Vue restreinte');

    test()->actingAs($directeur);
    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->assertDontSee('Vue restreinte');
});

test('un caissier sans droit d’encaissement ne voit pas la colonne Actions', function () {
    $establishment = Establishment::factory()->create();
    // gestionnaire a finance.access mais pas billing.manage : la colonne
    // Actions doit être masquée plutôt que rendue vide et muette.
    $gestionnaire = createUserWithRole($establishment, 'gestionnaire');
    actingInEstablishment($establishment);
    test()->actingAs($gestionnaire);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'registration_amount' => 1000,
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->assertDontSee('Encaisser')
        ->assertDontSee(route('billing.enrollments.show', $enrollment), false);
});

test('le pied de tableau indique le nombre d’élèves affichés', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $studentA = Student::factory()->create(['establishment_id' => $establishment->id]);
    $studentB = Student::factory()->create(['establishment_id' => $establishment->id]);

    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'student_id' => $studentA->id, 'registration_amount' => 500]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'student_id' => $studentB->id, 'registration_amount' => 500]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->assertSee('2 élèves affichés');
});

test('la liste est paginée au-delà de 25 élèves et change de page correctement', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);

    $students = Student::factory()->count(30)->create(['establishment_id' => $establishment->id]);
    foreach ($students as $index => $student) {
        Enrollment::factory()->create([
            'establishment_id' => $establishment->id,
            'school_year_id' => $schoolYear->id,
            'student_id' => $student->id,
            'registration_amount' => 1000 + $index,
        ]);
    }

    $component = Livewire::test(Index::class)->set('school_year_id', $schoolYear->id);

    expect($component->viewData('rows'))->toHaveCount(25)
        ->and($component->viewData('displayedCount'))->toBe(30);

    $component->call('nextPage');

    expect($component->viewData('rows'))->toHaveCount(5);
});

test('le lien de relance n’apparaît que pour un élève en retard', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $lateStudent = Student::factory()->create(['establishment_id' => $establishment->id]);
    $onTimeStudent = Student::factory()->create(['establishment_id' => $establishment->id]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $lateStudent->id,
        'registration_amount' => 1000,
        'total_paid' => 0,
    ]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $onTimeStudent->id,
        'registration_amount' => 1000,
        'total_paid' => 1000,
    ]);

    $component = Livewire::test(Index::class)->set('school_year_id', $schoolYear->id);

    $component->assertSee(route('reports.payment-reminder-pdf', $lateStudent));
    $component->assertDontSee(route('reports.payment-reminder-pdf', $onTimeStudent));
});

test('le lien d’échéance n’apparaît que pour un élève n’ayant pas soldé la prochaine tranche', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->addDays(20)]);

    $awaitingStudent = Student::factory()->create(['establishment_id' => $establishment->id]);
    $settledStudent = Student::factory()->create(['establishment_id' => $establishment->id]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $awaitingStudent->id,
        'registration_amount' => 5000,
        'installment_1_amount' => 10000,
        'total_paid' => 5000,
    ]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $settledStudent->id,
        'registration_amount' => 5000,
        'installment_1_amount' => 10000,
        'total_paid' => 15000,
    ]);

    $component = Livewire::test(Index::class)->set('school_year_id', $schoolYear->id);

    $component->assertSee(route('reports.payment-reminder-pdf', ['student' => $awaitingStudent, 'type' => 'upcoming']));
    $component->assertDontSee(route('reports.payment-reminder-pdf', ['student' => $settledStudent, 'type' => 'upcoming']));
});
