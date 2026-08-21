<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Installment;
use App\Domain\Billing\Services\PaymentTrackingService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;

test('un directeur peut consulter la planche de lettres de relance en ligne', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $response = $this->actingAs($directeur)->get(route('reports.payment-reminders-pdf'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('un enseignant sans accès à la facturation ne peut pas générer la planche', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);

    $response = $this->actingAs($teacher)->get(route('reports.payment-reminders-pdf'));

    $response->assertForbidden();
});

/**
 * @param  \Illuminate\Support\Collection<int, Enrollment>  $enrollments
 */
function renderPaymentRemindersBatchHtml($enrollments, ?SchoolYear $schoolYear = null, string $reminderType = 'late', ?Installment $nextInstallment = null): string
{
    $letters = $enrollments->map(function (Enrollment $enrollment): array {
        $reminder = paymentReminderRowsFor($enrollment);

        return [
            'student' => $enrollment->student,
            'establishment' => $enrollment->establishment,
            'classroom' => $enrollment->classroom,
            'rows' => $reminder['rows'],
            'total' => $reminder['total'],
        ];
    });

    return view('pdf.payment-reminders-batch', [
        'letters' => $letters,
        'schoolYear' => $schoolYear,
        'generalInformation' => GeneralInformation::current(),
        'reminderType' => $reminderType,
        'nextInstallment' => $nextInstallment,
    ])->render();
}

test('la planche contient une lettre par élève en retard et exclut les élèves à jour', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);

    $late = Student::factory()->create(['establishment_id' => $establishment->id, 'last_name' => 'Retard']);
    $onTime = Student::factory()->create(['establishment_id' => $establishment->id, 'last_name' => 'Ajour']);

    $lateEnrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $late->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 0,
    ]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $onTime->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 5000,
    ]);

    $html = renderPaymentRemindersBatchHtml(collect([$lateEnrollment->fresh()->load(['student', 'classroom', 'establishment'])]), $schoolYear);

    expect($html)->toContain('RETARD')
        ->and($html)->not->toContain('AJOUR');
});

test('un message s’affiche quand aucun élève n’est en retard sur le périmètre', function () {
    $html = renderPaymentRemindersBatchHtml(collect(), null);

    expect($html)->toContain('Aucun élève en retard sur ce périmètre.');
});

test('le filtre par niveau exclut un élève en retard d’un autre niveau', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $levelA = Level::factory()->create();
    $levelB = Level::factory()->create();
    $classroomA = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $levelA->id]);
    $classroomB = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'level_id' => $levelB->id]);

    $studentA = Student::factory()->create(['establishment_id' => $establishment->id, 'last_name' => 'NiveauA']);
    $studentB = Student::factory()->create(['establishment_id' => $establishment->id, 'last_name' => 'NiveauB']);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $studentA->id,
        'classroom_id' => $classroomA->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 0,
    ]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $studentB->id,
        'classroom_id' => $classroomB->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 0,
    ]);

    $response = $this->actingAs($directeur)->get(route('reports.payment-reminders-pdf', ['level_id' => $levelA->id]));
    $response->assertOk();

    $lateStudentIds = app(\App\Domain\Billing\Services\PaymentTrackingService::class)
        ->balances($schoolYear->id)
        ->filter(fn (array $row) => $row['balance'] > 0)
        ->pluck('student_id');

    $filtered = Enrollment::whereIn('student_id', $lateStudentIds)
        ->where('school_year_id', $schoolYear->id)
        ->where('status', 'active')
        ->whereHas('classroom', fn ($query) => $query->where('level_id', $levelA->id))
        ->pluck('student_id');

    expect($filtered)->toContain($studentA->id)
        ->and($filtered)->not->toContain($studentB->id);
});

test('un élève en retard d’un autre établissement n’est pas inclus dans la planche (isolation tenant)', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();

    actingInEstablishment($establishmentA);
    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroomA = Classroom::factory()->create(['establishment_id' => $establishmentA->id, 'school_year_id' => $schoolYear->id]);
    $studentA = Student::factory()->create(['establishment_id' => $establishmentA->id, 'last_name' => 'EtablissementA']);
    Enrollment::factory()->create([
        'establishment_id' => $establishmentA->id,
        'student_id' => $studentA->id,
        'classroom_id' => $classroomA->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 0,
    ]);

    actingInEstablishment($establishmentB);
    $classroomB = Classroom::factory()->create(['establishment_id' => $establishmentB->id, 'school_year_id' => $schoolYear->id]);
    $studentB = Student::factory()->create(['establishment_id' => $establishmentB->id, 'last_name' => 'EtablissementB']);
    Enrollment::factory()->create([
        'establishment_id' => $establishmentB->id,
        'student_id' => $studentB->id,
        'classroom_id' => $classroomB->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 0,
    ]);

    $lateStudentIds = app(\App\Domain\Billing\Services\PaymentTrackingService::class)
        ->balances($schoolYear->id)
        ->filter(fn (array $row) => $row['balance'] > 0)
        ->pluck('student_id');

    expect($lateStudentIds)->toContain($studentB->id)
        ->and($lateStudentIds)->not->toContain($studentA->id);
});

test('un directeur peut consulter la planche de rappels d’échéance en ligne', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $response = $this->actingAs($directeur)->get(route('reports.payment-reminders-pdf', ['type' => 'upcoming']));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('la planche d’échéance cible les élèves n’ayant pas soldé la prochaine tranche, qu’ils soient en retard ou non', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'position' => 1, 'due_date' => now()->addDays(20)]);

    $onTimeButAwaiting = Student::factory()->create(['establishment_id' => $establishment->id]);
    $alreadySettled = Student::factory()->create(['establishment_id' => $establishment->id]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $onTimeButAwaiting->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'installment_1_amount' => 10000,
        'total_paid' => 5000,
    ]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $alreadySettled->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'installment_1_amount' => 10000,
        'total_paid' => 15000,
    ]);

    $awaitingStudentIds = app(PaymentTrackingService::class)
        ->studentsAwaitingInstallment($installment)
        ->pluck('student_id');

    expect($awaitingStudentIds)->toContain($onTimeButAwaiting->id)
        ->and($awaitingStudentIds)->not->toContain($alreadySettled->id);
});

test('un message distinct s’affiche selon la raison de l’absence de résultat sur la planche d’échéance', function () {
    $htmlNoInstallment = renderPaymentRemindersBatchHtml(collect(), null, 'upcoming', null);
    expect($htmlNoInstallment)->toContain("Aucune échéance à venir n'est configurée");

    $installment = Installment::factory()->create(['due_date' => now()->addDays(20)]);
    $htmlAllSettled = renderPaymentRemindersBatchHtml(collect(), null, 'upcoming', $installment);
    expect($htmlAllSettled)->toContain('Tous les élèves ont déjà soldé la prochaine échéance');
});
