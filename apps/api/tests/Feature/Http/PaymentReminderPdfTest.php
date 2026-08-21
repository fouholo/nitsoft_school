<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Installment;
use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Enums\GuardianRelationship;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;

test('un membre de l’établissement peut consulter la lettre de relance en ligne quand le solde est positif', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 0,
    ]);

    $response = $this->actingAs($directeur)->get(route('reports.payment-reminder-pdf', $student));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('le paramètre download force le téléchargement de la lettre', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 0,
    ]);

    $response = $this->actingAs($directeur)->get(route('reports.payment-reminder-pdf', ['student' => $student, 'download' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('aucune lettre n’est générée quand le solde n’est pas positif', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 5000,
    ]);

    $response = $this->actingAs($directeur)->get(route('reports.payment-reminder-pdf', $student));

    $response->assertNotFound();
});

test('un membre d’un autre établissement ne peut même pas résoudre l’élève (isolation tenant)', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();
    $adminB = createUserWithRole($establishmentB, 'directeur');

    actingInEstablishment($establishmentA);
    $student = Student::factory()->create(['establishment_id' => $establishmentA->id]);

    actingInEstablishment($establishmentB);

    $response = $this->actingAs($adminB)->get(route('reports.payment-reminder-pdf', $student));

    $response->assertNotFound();
});

test('un élève à jour sur son passé mais n’ayant pas soldé la prochaine échéance reçoit un rappel', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'label' => 'Novembre', 'position' => 1, 'due_date' => now()->addDays(20)]);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'installment_1_amount' => 10000,
        'total_paid' => 5000,
    ]);

    // Sans le type "upcoming", cet élève n'est pas en retard (rien n'est encore échu).
    $lateResponse = $this->actingAs($directeur)->get(route('reports.payment-reminder-pdf', $student));
    $lateResponse->assertNotFound();

    $upcomingResponse = $this->actingAs($directeur)->get(route('reports.payment-reminder-pdf', ['student' => $student, 'type' => 'upcoming']));
    $upcomingResponse->assertOk();
});

test('aucun rappel d’échéance n’est généré si l’élève l’a déjà soldée', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
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
        'registration_amount' => 5000,
        'installment_1_amount' => 10000,
        'total_paid' => 15000,
    ]);

    $response = $this->actingAs($directeur)->get(route('reports.payment-reminder-pdf', ['student' => $student, 'type' => 'upcoming']));

    $response->assertNotFound();
});

test('aucun rappel d’échéance n’est généré si aucune échéance à venir n’est configurée', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 0,
    ]);

    $response = $this->actingAs($directeur)->get(route('reports.payment-reminder-pdf', ['student' => $student, 'type' => 'upcoming']));

    $response->assertNotFound();
});

function renderPaymentReminderHtml(Student $student, Enrollment $enrollment, string $reminderType = 'late', ?Installment $nextInstallment = null): string
{
    $reminder = paymentReminderRowsFor($enrollment);

    return view('pdf.payment-reminder', [
        'student' => $student,
        'establishment' => $student->establishment,
        'classroom' => $enrollment->classroom,
        'schoolYear' => $enrollment->schoolYear,
        'rows' => $reminder['rows'],
        'total' => $reminder['total'],
        'generalInformation' => GeneralInformation::current(),
        'reminderType' => $reminderType,
        'nextInstallment' => $nextInstallment,
    ])->render();
}

test('la lettre affiche l’identité, la classe, l’année et le solde des postes non soldés', function () {
    $establishment = Establishment::factory()->create(['name' => 'Groupe Scolaire Excellence']);
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['label' => '2026-2027']);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'label' => 'Octobre', 'position' => 1]);
    Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'label' => 'Novembre', 'position' => 2]);

    $student = Student::factory()->create(['establishment_id' => $establishment->id, 'last_name' => 'Traoré', 'first_name' => 'Awa']);
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'installment_1_amount' => 10000,
        'installment_2_amount' => 8000,
        'total_paid' => 3000,
    ]);

    $html = renderPaymentReminderHtml($student, $enrollment->fresh());

    expect($html)->toContain('TRAORÉ')
        ->and($html)->toContain('Awa')
        ->and($html)->toContain($classroom->fresh()->name)
        ->and($html)->toContain('2026-2027')
        ->and($html)->toContain('Frais d&#039;inscription')
        ->and($html)->toContain('Octobre')
        ->and($html)->toContain('Groupe Scolaire Excellence');
});

test('un poste déjà entièrement soldé n’apparaît pas dans le tableau', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 5000,
    ]);

    $html = renderPaymentReminderHtml($student, $enrollment->fresh());

    expect($html)->not->toContain("Frais d'inscription");
});

test('le tuteur principal nommé est utilisé comme destinataire', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 0,
    ]);

    $guardian = Guardian::factory()->create(['first_name' => 'Fatou', 'last_name' => 'Koné']);
    $student->guardians()->attach($guardian->id, [
        'establishment_id' => $establishment->id,
        'status' => GuardianLinkStatus::Approved,
        'relationship' => GuardianRelationship::Mere,
        'is_primary_contact' => true,
    ]);

    $html = renderPaymentReminderHtml($student->fresh(), $enrollment->fresh());

    expect($html)->toContain('Fatou Koné');
});

test('sans tuteur principal, une formule générique est utilisée', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id, 'first_name' => 'Awa', 'last_name' => 'Traoré']);
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'total_paid' => 0,
    ]);

    $html = renderPaymentReminderHtml($student->fresh(), $enrollment->fresh());

    expect($html)->toContain("tuteur/tutrice de Awa Traoré");
});

test('la lettre de type échéance annonce la prochaine tranche plutôt qu’un retard', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $installment = Installment::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id, 'label' => 'Novembre', 'position' => 1, 'due_date' => now()->addDays(20)]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'registration_amount' => 5000,
        'installment_1_amount' => 10000,
        'total_paid' => 5000,
    ]);

    $html = renderPaymentReminderHtml($student->fresh(), $enrollment->fresh(), 'upcoming', $installment);

    expect($html)->toContain('Novembre')
        ->and($html)->toContain($installment->due_date->format('d/m/Y'))
        ->and($html)->toContain("n'est pas encore soldée")
        ->and($html)->not->toContain('présente à ce jour un solde impayé');
});
