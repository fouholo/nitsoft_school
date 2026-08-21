<?php

declare(strict_types=1);

use App\Domain\Arabic\Models\ArabicGrade;
use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicReportCard;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicSubjectCoefficient;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    actingInEstablishment($this->establishment);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);
    $arabicTerm = ArabicTerm::factory()->create(['establishment_id' => $this->establishment->id]);
    $arabicSubject = ArabicSubject::factory()->create(['name' => 'القرآن الكريم']);

    ArabicSubjectCoefficient::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_level_id' => $arabicLevel->id,
        'arabic_subject_id' => $arabicSubject->id,
        'coefficient' => 3,
    ]);

    $student = Student::factory()->create(['establishment_id' => $this->establishment->id]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $student->id,
        'arabic_level_id' => $arabicLevel->id,
    ]);

    $gradeSheet = ArabicGradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_level_id' => $arabicLevel->id,
        'arabic_subject_id' => $arabicSubject->id,
        'arabic_term_id' => $arabicTerm->id,
    ]);

    ArabicGrade::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_grade_sheet_id' => $gradeSheet->id,
        'enrollment_id' => $enrollment->id,
        'score' => 15,
    ]);

    $this->arabicReportCard = ArabicReportCard::create([
        'establishment_id' => $this->establishment->id,
        'enrollment_id' => $enrollment->id,
        'arabic_term_id' => $arabicTerm->id,
        'average' => 15.0,
        'rank' => 1,
        'appreciation' => 'Bien',
        'generated_at' => now(),
    ]);
});

test('un membre de l’établissement peut consulter le PDF du bulletin arabe en ligne', function () {
    $directeur = createUserWithRole($this->establishment, 'directeur');

    $response = $this->actingAs($directeur)->get(route('arabic.report-cards.pdf', $this->arabicReportCard));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

test('le paramètre download force le téléchargement du PDF', function () {
    $directeur = createUserWithRole($this->establishment, 'directeur');

    $response = $this->actingAs($directeur)->get(route('arabic.report-cards.pdf', ['arabicReportCard' => $this->arabicReportCard, 'download' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('un membre d’un autre établissement ne peut pas résoudre le bulletin (isolation tenant)', function () {
    $establishmentB = Establishment::factory()->create(['is_arabe' => true]);
    $adminB = createUserWithRole($establishmentB, 'directeur');

    actingInEstablishment($establishmentB);

    $response = $this->actingAs($adminB)->get(route('arabic.report-cards.pdf', $this->arabicReportCard));

    $response->assertNotFound();
});
