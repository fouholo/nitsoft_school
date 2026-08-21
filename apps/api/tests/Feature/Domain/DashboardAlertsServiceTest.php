<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Academics\Models\Term;
use App\Domain\Billing\Models\Installment;
use App\Domain\Dashboard\Services\DashboardAlertsService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\ReportCard;

// --- Factures en retard ------------------------------------------------

test('une échéance dépassée non soldée génère une alerte avec le solde restant', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->subDay()]);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'installment_1_amount' => 10000,
        'total_paid' => 0,
    ]);

    $items = (new DashboardAlertsService)->overdueInvoices([$establishment->id]);

    expect($items)->toHaveCount(1)
        ->and($items[0]['type'])->toBe('overdue_invoices')
        ->and($items[0]['label'])->toContain('1 facture')
        ->and($items[0]['establishment_id'])->toBe($establishment->id);
});

test('aucune échéance non encore due ne génère d’alerte', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->addDays(20)]);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'installment_1_amount' => 10000,
        'total_paid' => 0,
    ]);

    expect((new DashboardAlertsService)->overdueInvoices([$establishment->id]))->toBe([]);
});

// --- Classes sans enseignant --------------------------------------------

test('une classe active sans aucun enseignant affecté génère une alerte', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);

    $items = (new DashboardAlertsService)->classroomsWithoutTeacher([$establishment->id]);

    expect($items)->toHaveCount(1)
        ->and($items[0]['type'])->toBe('classroom_without_teacher')
        ->and($items[0]['establishment_id'])->toBe($establishment->id);
});

test('une classe avec au moins un enseignant affecté ne génère aucune alerte', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    TeacherAssignment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
    ]);

    expect((new DashboardAlertsService)->classroomsWithoutTeacher([$establishment->id]))->toBe([]);
});

// --- Effectif dépassé -----------------------------------------------------

test('une classe dont l’effectif actif dépasse la capacité génère une alerte', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'capacity' => 2]);

    Student::factory()->count(3)->create(['establishment_id' => $establishment->id])->each(
        fn (Student $student) => Enrollment::factory()->create([
            'establishment_id' => $establishment->id,
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'school_year_id' => $schoolYear->id,
        ]),
    );

    $items = (new DashboardAlertsService)->classroomsOverCapacity([$establishment->id]);

    expect($items)->toHaveCount(1)
        ->and($items[0]['type'])->toBe('classroom_over_capacity')
        ->and($items[0]['label'])->toContain('3/2');
});

test('une classe sans capacité renseignée ne génère jamais d’alerte d’effectif', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'capacity' => null]);

    Student::factory()->count(5)->create(['establishment_id' => $establishment->id])->each(
        fn (Student $student) => Enrollment::factory()->create([
            'establishment_id' => $establishment->id,
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'school_year_id' => $schoolYear->id,
        ]),
    );

    expect((new DashboardAlertsService)->classroomsOverCapacity([$establishment->id]))->toBe([]);
});

test('une classe dont l’effectif ne dépasse pas la capacité ne génère aucune alerte', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'capacity' => 30]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
    ]);

    expect((new DashboardAlertsService)->classroomsOverCapacity([$establishment->id]))->toBe([]);
});

// --- Bulletins non finalisés ------------------------------------------

test('un élève sans bulletin finalisé sur un trimestre terminé génère une alerte', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $term = Term::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'ends_on' => now()->subWeek()]);

    $finalized = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $finalized->id, 'classroom_id' => $classroom->id, 'school_year_id' => $schoolYear->id]);
    ReportCard::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $finalized->id, 'classroom_id' => $classroom->id, 'term_id' => $term->id, 'generated_at' => now()]);

    $missing = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $missing->id, 'classroom_id' => $classroom->id, 'school_year_id' => $schoolYear->id]);

    $items = (new DashboardAlertsService)->unfinalizedReportCards([$establishment->id]);

    expect($items)->toHaveCount(1)
        ->and($items[0]['type'])->toBe('unfinalized_report_cards')
        ->and($items[0]['label'])->toContain('1 bulletin(s)');
});

test('un trimestre non terminé ne génère aucune alerte de bulletin', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    Term::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'ends_on' => now()->addWeek()]);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $student->id, 'classroom_id' => $classroom->id, 'school_year_id' => $schoolYear->id]);

    expect((new DashboardAlertsService)->unfinalizedReportCards([$establishment->id]))->toBe([]);
});

test('un établissement préscolaire/primaire ne génère jamais d’alerte de bulletin', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    // Un Term existe malgré tout (donnée incohérente hypothétique) : le
    // filtrage par type d'établissement doit rester la garde principale.
    Term::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'ends_on' => now()->subWeek()]);

    expect((new DashboardAlertsService)->unfinalizedReportCards([$establishment->id]))->toBe([]);
});

// --- Établissement autre que le tenant courant (fondateur multi-écoles) --

test('une classe d’une autre école que le tenant courant avec un enseignant n’est pas signalée à tort', function () {
    $foundation = \App\Domain\Establishments\Models\Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id]);

    actingInEstablishment($schoolA);
    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    Classroom::factory()->create(['establishment_id' => $schoolA->id, 'school_year_id' => $schoolYear->id]);

    $classroomB = Classroom::factory()->create(['establishment_id' => $schoolB->id, 'school_year_id' => $schoolYear->id]);
    TeacherAssignment::factory()->create(['establishment_id' => $schoolB->id, 'classroom_id' => $classroomB->id, 'school_year_id' => $schoolYear->id]);

    // Le tenant courant reste A : la classe de B, qui a bien un enseignant,
    // ne doit jamais apparaître dans les alertes malgré ça.
    $items = (new DashboardAlertsService)->classroomsWithoutTeacher([$schoolB->id]);

    expect($items)->toBe([]);
});

test('l’effectif d’une autre école que le tenant courant est correctement compté', function () {
    $foundation = \App\Domain\Establishments\Models\Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id]);

    actingInEstablishment($schoolA);
    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);

    actingInEstablishment($schoolB);
    $classroomB = Classroom::factory()->create(['establishment_id' => $schoolB->id, 'school_year_id' => $schoolYear->id, 'capacity' => 2]);
    Student::factory()->count(3)->create(['establishment_id' => $schoolB->id])->each(
        fn (Student $student) => Enrollment::factory()->create([
            'establishment_id' => $schoolB->id,
            'student_id' => $student->id,
            'classroom_id' => $classroomB->id,
            'school_year_id' => $schoolYear->id,
        ]),
    );

    // Le tenant courant redevient A avant l'appel : l'effectif de B doit
    // quand même être correctement compté (pas silencieusement à zéro).
    actingInEstablishment($schoolA);

    $items = (new DashboardAlertsService)->classroomsOverCapacity([$schoolB->id]);

    expect($items)->toHaveCount(1)
        ->and($items[0]['label'])->toContain('3/2');
});

test('les bulletins manquants d’une autre école que le tenant courant sont détectés', function () {
    $foundation = \App\Domain\Establishments\Models\Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id, 'type' => EstablishmentType::Secondaire]);

    actingInEstablishment($schoolB);
    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroomB = Classroom::factory()->create(['establishment_id' => $schoolB->id, 'school_year_id' => $schoolYear->id]);
    $termB = Term::factory()->create(['establishment_id' => $schoolB->id, 'school_year_id' => $schoolYear->id, 'ends_on' => now()->subWeek()]);
    $student = Student::factory()->create(['establishment_id' => $schoolB->id]);
    Enrollment::factory()->create(['establishment_id' => $schoolB->id, 'student_id' => $student->id, 'classroom_id' => $classroomB->id, 'school_year_id' => $schoolYear->id]);

    // Le tenant courant redevient A avant l'appel.
    actingInEstablishment($schoolA);

    $items = (new DashboardAlertsService)->unfinalizedReportCards([$schoolB->id]);

    expect($items)->toHaveCount(1);
});

// --- Agrégation multi-établissements -------------------------------------

test('les alertes de plusieurs établissements sont taguées avec le bon nom d’établissement', function () {
    $establishmentA = Establishment::factory()->create(['name' => 'École A']);
    $establishmentB = Establishment::factory()->create(['name' => 'École B']);

    actingInEstablishment($establishmentA);
    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    Classroom::factory()->create(['establishment_id' => $establishmentA->id, 'school_year_id' => $schoolYear->id]);

    actingInEstablishment($establishmentB);
    Classroom::factory()->create(['establishment_id' => $establishmentB->id, 'school_year_id' => $schoolYear->id]);

    $items = (new DashboardAlertsService)->classroomsWithoutTeacher([$establishmentA->id, $establishmentB->id]);

    expect($items)->toHaveCount(2);
    expect(collect($items)->pluck('establishmentName')->all())->toEqualCanonicalizing(['École A', 'École B']);
});
