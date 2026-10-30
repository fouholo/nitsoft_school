<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicGrade;
use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicReportCard;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicSubjectCoefficient;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Arabic\Services\ArabicReportCardService;
use App\Domain\Enrollment\Enums\Track;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Models\Establishment;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    actingInEstablishment($this->establishment);

    $this->schoolYear = SchoolYear::factory()->create();
    $this->arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);
    $this->arabicClassroom = ArabicClassroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicLevel->id,
    ]);
    $this->arabicTerm = ArabicTerm::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $this->arabicSubjectA = ArabicSubject::factory()->create(['name' => 'القرآن الكريم']);
    $this->arabicSubjectB = ArabicSubject::factory()->create(['name' => 'اللغة العربية']);

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
        'arabic_classroom_id' => $enrollment->arabic_classroom_id,
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

test('la moyenne générale est la moyenne simple des matières, sans coefficient, regroupant des élèves de classes françaises différentes', function () {
    $enrollmentA = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
    ]);
    $enrollmentB = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
    ]);
    expect($enrollmentA->classroom_id)->not->toBe($enrollmentB->classroom_id);

    makeArabicGrade($enrollmentA, $this->arabicTerm, $this->arabicSubjectA, 16);
    makeArabicGrade($enrollmentA, $this->arabicTerm, $this->arabicSubjectB, 10);

    makeArabicGrade($enrollmentB, $this->arabicTerm, $this->arabicSubjectA, 8);
    makeArabicGrade($enrollmentB, $this->arabicTerm, $this->arabicSubjectB, 20);

    $service = new ArabicReportCardService;
    $reportCards = $service->generate($this->arabicClassroom, $this->arabicTerm);

    expect($reportCards)->toHaveCount(2);

    $cardA = ArabicReportCard::where('enrollment_id', $enrollmentA->id)->sole();
    $cardB = ArabicReportCard::where('enrollment_id', $enrollmentB->id)->sole();

    // A : (16 + 10) / 2 = 13.0 — B : (8 + 20) / 2 = 14.0
    expect((float) $cardA->average)->toBe(13.0)
        ->and((float) $cardB->average)->toBe(14.0)
        ->and($cardB->rank)->toBe(1)
        ->and($cardA->rank)->toBe(2);
});

test('le coefficient d’une évaluation et les coefficients configurés n’ont aucun effet, et le barème reste ramené sur 20', function () {
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
    ]);

    // Coran : 10/20 (coefficient d'évaluation 5) et 5/10 = 10/20 ramené → 10 ;
    // langue : 18/20. Moyenne simple : (10 + 18) / 2 = 14.
    $sheet = makeArabicGrade($enrollment, $this->arabicTerm, $this->arabicSubjectA, 10)->arabicGradeSheet;
    $sheet->update(['weight' => 5]);
    makeArabicGrade($enrollment, $this->arabicTerm, $this->arabicSubjectA, 5, 10);
    makeArabicGrade($enrollment, $this->arabicTerm, $this->arabicSubjectB, 18);

    ArabicSubjectCoefficient::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_level_id' => $this->arabicLevel->id,
        'arabic_subject_id' => $this->arabicSubjectA->id,
        'coefficient' => 9,
    ]);

    (new ArabicReportCardService)->generate($this->arabicClassroom, $this->arabicTerm);

    expect((float) ArabicReportCard::sole()->average)->toBe(14.0);
});

test('regénérer un bulletin met à jour la moyenne existante plutôt que d’en créer un nouveau', function () {
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
    ]);

    makeArabicGrade($enrollment, $this->arabicTerm, $this->arabicSubjectA, 10);
    makeArabicGrade($enrollment, $this->arabicTerm, $this->arabicSubjectB, 10);

    $service = new ArabicReportCardService;
    $service->generate($this->arabicClassroom, $this->arabicTerm);

    ArabicGrade::where('enrollment_id', $enrollment->id)->update(['score' => 20]);

    $service->generate($this->arabicClassroom, $this->arabicTerm);

    expect(ArabicReportCard::count())->toBe(1)
        ->and((float) ArabicReportCard::sole()->average)->toBe(20.0);
});

test('subjectBreakdown détaille chaque matière avec sa moyenne', function () {
    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
    ]);

    makeArabicGrade($enrollment, $this->arabicTerm, $this->arabicSubjectA, 16);
    makeArabicGrade($enrollment, $this->arabicTerm, $this->arabicSubjectB, 10);

    $service = new ArabicReportCardService;
    $service->generate($this->arabicClassroom, $this->arabicTerm);

    $reportCard = ArabicReportCard::sole();
    $breakdown = $service->subjectBreakdown($reportCard);

    expect($breakdown)->toHaveCount(2);

    $rowA = $breakdown->firstWhere(fn ($row) => $row->subject->is($this->arabicSubjectA));
    expect($rowA->average)->toBe(16.0);
});

test('le classement couvre la classe arabe, filière principale ou secondaire, et ignore les autres classes arabes', function () {
    $mainArabic = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'classroom_id' => null,
        'arabic_classroom_id' => $this->arabicClassroom->id,
        'main_track' => Track::Arabe,
    ]);
    $secondaryArabic = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
    ]);

    $otherClassroom = ArabicClassroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicLevel->id,
    ]);
    $elsewhere = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_classroom_id' => $otherClassroom->id,
    ]);

    makeArabicGrade($mainArabic, $this->arabicTerm, $this->arabicSubjectA, 12);
    makeArabicGrade($secondaryArabic, $this->arabicTerm, $this->arabicSubjectA, 18);
    makeArabicGrade($elsewhere, $this->arabicTerm, $this->arabicSubjectA, 20);

    (new ArabicReportCardService)->generate($this->arabicClassroom, $this->arabicTerm);

    expect(ArabicReportCard::where('enrollment_id', $secondaryArabic->id)->sole()->rank)->toBe(1)
        ->and(ArabicReportCard::where('enrollment_id', $mainArabic->id)->sole()->rank)->toBe(2)
        ->and(ArabicReportCard::where('enrollment_id', $elsewhere->id)->exists())->toBeFalse();
});
