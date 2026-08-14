<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\SubjectCoefficient;
use App\Domain\Academics\Models\Term;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\Grade;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Services\ReportCardService;

function makeGradedStudent(Establishment $establishment, Classroom $classroom, Term $term, array $gradesBySheet): Student
{
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'status' => 'active',
    ]);

    foreach ($gradesBySheet as [$gradeSheet, $score]) {
        Grade::factory()->create([
            'establishment_id' => $establishment->id,
            'grade_sheet_id' => $gradeSheet->id,
            'student_id' => $student->id,
            'score' => $score,
        ]);
    }

    return $student;
}

test('la moyenne pondérée et le rang sont calculés correctement', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $term = Term::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $subjectA = Subject::factory()->create();
    $subjectB = Subject::factory()->create();

    $sheetA = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subjectA->id,
        'term_id' => $term->id,
        'max_score' => 20,
        'weight' => 2,
    ]);
    $sheetB = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subjectB->id,
        'term_id' => $term->id,
        'max_score' => 20,
        'weight' => 1,
    ]);

    SubjectCoefficient::factory()->create(['establishment_id' => $establishment->id, 'level_id' => $classroom->level_id, 'serie_id' => null, 'subject_id' => $subjectA->id, 'coefficient' => 2]);
    SubjectCoefficient::factory()->create(['establishment_id' => $establishment->id, 'level_id' => $classroom->level_id, 'serie_id' => null, 'subject_id' => $subjectB->id, 'coefficient' => 1]);

    $best = makeGradedStudent($establishment, $classroom, $term, [[$sheetA, 16], [$sheetB, 10]]);
    $worst = makeGradedStudent($establishment, $classroom, $term, [[$sheetA, 8], [$sheetB, 20]]);

    $reportCards = (new ReportCardService)->generateForClassroomAndTerm($classroom, $term);

    expect($reportCards)->toHaveCount(2);

    $bestCard = $reportCards->firstWhere('student_id', $best->id);
    $worstCard = $reportCards->firstWhere('student_id', $worst->id);

    expect((float) $bestCard->average)->toBe(14.0)
        ->and($bestCard->rank)->toBe(1)
        ->and((float) $worstCard->average)->toBe(12.0)
        ->and($worstCard->rank)->toBe(2);
});

test('les élèves ex-aequo partagent le même rang', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $term = Term::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $subject = Subject::factory()->create();

    $sheet = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'term_id' => $term->id,
        'max_score' => 20,
        'weight' => 1,
    ]);

    SubjectCoefficient::factory()->create(['establishment_id' => $establishment->id, 'level_id' => $classroom->level_id, 'serie_id' => null, 'subject_id' => $subject->id, 'coefficient' => 1]);

    $first = makeGradedStudent($establishment, $classroom, $term, [[$sheet, 15]]);
    $second = makeGradedStudent($establishment, $classroom, $term, [[$sheet, 15]]);
    $third = makeGradedStudent($establishment, $classroom, $term, [[$sheet, 10]]);

    $reportCards = (new ReportCardService)->generateForClassroomAndTerm($classroom, $term);

    $rankFor = fn ($studentId) => $reportCards->firstWhere('student_id', $studentId)->rank;

    expect($rankFor($first->id))->toBe(1)
        ->and($rankFor($second->id))->toBe(1)
        ->and($rankFor($third->id))->toBe(3);
});

test('le détail par matière du bulletin liste chaque matière notée avec sa moyenne', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $term = Term::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $maths = Subject::factory()->create(['name' => 'Mathématiques']);
    $francais = Subject::factory()->create(['name' => 'Français']);

    $sheetMaths = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $maths->id,
        'term_id' => $term->id,
        'max_score' => 20,
        'weight' => 1,
    ]);
    $sheetFrancais = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $francais->id,
        'term_id' => $term->id,
        'max_score' => 20,
        'weight' => 1,
    ]);

    SubjectCoefficient::factory()->create(['establishment_id' => $establishment->id, 'level_id' => $classroom->level_id, 'serie_id' => null, 'subject_id' => $maths->id, 'coefficient' => 1]);
    SubjectCoefficient::factory()->create(['establishment_id' => $establishment->id, 'level_id' => $classroom->level_id, 'serie_id' => null, 'subject_id' => $francais->id, 'coefficient' => 1]);

    $student = makeGradedStudent($establishment, $classroom, $term, [[$sheetMaths, 18], [$sheetFrancais, 12]]);

    $service = new ReportCardService;
    $reportCard = $service->generateForClassroomAndTerm($classroom, $term)->firstWhere('student_id', $student->id);

    $breakdown = $service->subjectBreakdown($reportCard);

    expect($breakdown)->toHaveCount(2);

    $mathsRow = $breakdown->firstWhere('subject.id', $maths->id);
    $francaisRow = $breakdown->firstWhere('subject.id', $francais->id);

    expect($mathsRow->average)->toBe(18.0)
        ->and($francaisRow->average)->toBe(12.0);
});

test('la génération de bulletin est refusée pour une classe préscolaire', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->prescolaire()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $term = Term::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);

    expect(fn () => (new ReportCardService)->generateForClassroomAndTerm($classroom, $term))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});
