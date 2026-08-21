<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicGrade;
use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicReportCard;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicSubjectCoefficient;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Arabic\Services\ArabicReportCardService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    actingInEstablishment($this->establishment);

    $this->schoolYear = SchoolYear::factory()->create();
    $this->arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);
    $this->arabicTerm = ArabicTerm::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $this->arabicSubjectA = ArabicSubject::factory()->create(['name' => 'القرآن الكريم']);
    $this->arabicSubjectB = ArabicSubject::factory()->create(['name' => 'اللغة العربية']);

    ArabicSubjectCoefficient::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_level_id' => $this->arabicLevel->id,
        'arabic_subject_id' => $this->arabicSubjectA->id,
        'coefficient' => 4,
    ]);
    ArabicSubjectCoefficient::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_level_id' => $this->arabicLevel->id,
        'arabic_subject_id' => $this->arabicSubjectB->id,
        'coefficient' => 2,
    ]);
});

function makeArabicGrade(Enrollment $enrollment, ArabicTerm $term, ArabicSubject $subject, float $score, float $maxScore = 20): ArabicGrade
{
    $gradeSheet = ArabicGradeSheet::factory()->create([
        'establishment_id' => $enrollment->establishment_id,
        'arabic_level_id' => $enrollment->arabic_level_id,
        'arabic_serie_id' => $enrollment->arabic_serie_id,
        'arabic_subject_id' => $subject->id,
        'arabic_term_id' => $term->id,
        'max_score' => $maxScore,
        'weight' => 1,
    ]);

    return ArabicGrade::factory()->create([
        'establishment_id' => $enrollment->establishment_id,
        'arabic_grade_sheet_id' => $gradeSheet->id,
        'enrollment_id' => $enrollment->id,
        'score' => $score,
    ]);
}

test('la moyenne générale est pondérée par coefficient, regroupant des élèves de classes françaises différentes', function () {
    $enrollmentA = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicLevel->id,
    ]);
    $enrollmentB = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicLevel->id,
    ]);
    expect($enrollmentA->classroom_id)->not->toBe($enrollmentB->classroom_id);

    makeArabicGrade($enrollmentA, $this->arabicTerm, $this->arabicSubjectA, 16);
    makeArabicGrade($enrollmentA, $this->arabicTerm, $this->arabicSubjectB, 10);

    makeArabicGrade($enrollmentB, $this->arabicTerm, $this->arabicSubjectA, 8);
    makeArabicGrade($enrollmentB, $this->arabicTerm, $this->arabicSubjectB, 20);

    $service = new ArabicReportCardService;
    $reportCards = $service->generate($this->arabicLevel, null, $this->arabicTerm);

    expect($reportCards)->toHaveCount(2);

    $cardA = ArabicReportCard::where('enrollment_id', $enrollmentA->id)->sole();
    $cardB = ArabicReportCard::where('enrollment_id', $enrollmentB->id)->sole();

    // A : (16*4 + 10*2) / 6 = 14.0 — B : (8*4 + 20*2) / 6 = 12.0
    expect((float) $cardA->average)->toBe(14.0)
        ->and((float) $cardB->average)->toBe(12.0)
        ->and($cardA->rank)->toBe(1)
        ->and($cardB->rank)->toBe(2);
});

test('erreur explicite si un coefficient est manquant pour une matière notée', function () {
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicLevel->id,
    ]);

    $arabicSubjectSansCoefficient = ArabicSubject::factory()->create();
    makeArabicGrade($enrollment, $this->arabicTerm, $arabicSubjectSansCoefficient, 15);

    $service = new ArabicReportCardService;

    expect(fn () => $service->generate($this->arabicLevel, null, $this->arabicTerm))
        ->toThrow(ValidationException::class);
});

test('regénérer un bulletin met à jour la moyenne existante plutôt que d’en créer un nouveau', function () {
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicLevel->id,
    ]);

    makeArabicGrade($enrollment, $this->arabicTerm, $this->arabicSubjectA, 10);
    makeArabicGrade($enrollment, $this->arabicTerm, $this->arabicSubjectB, 10);

    $service = new ArabicReportCardService;
    $service->generate($this->arabicLevel, null, $this->arabicTerm);

    ArabicGrade::where('enrollment_id', $enrollment->id)->update(['score' => 20]);

    $service->generate($this->arabicLevel, null, $this->arabicTerm);

    expect(ArabicReportCard::count())->toBe(1)
        ->and((float) ArabicReportCard::sole()->average)->toBe(20.0);
});

test('subjectBreakdown détaille chaque matière avec coefficient et enseignant', function () {
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicLevel->id,
    ]);

    makeArabicGrade($enrollment, $this->arabicTerm, $this->arabicSubjectA, 16);
    makeArabicGrade($enrollment, $this->arabicTerm, $this->arabicSubjectB, 10);

    $service = new ArabicReportCardService;
    $service->generate($this->arabicLevel, null, $this->arabicTerm);

    $reportCard = ArabicReportCard::sole();
    $breakdown = $service->subjectBreakdown($reportCard);

    expect($breakdown)->toHaveCount(2);

    $rowA = $breakdown->firstWhere(fn ($row) => $row->subject->is($this->arabicSubjectA));
    expect($rowA->average)->toBe(16.0)
        ->and($rowA->coefficient)->toBe(4.0);
});
