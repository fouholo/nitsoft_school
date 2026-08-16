<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\SubjectCoefficient;
use App\Domain\Academics\Models\Term;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\AppreciationScale;
use App\Domain\Grading\Models\Grade;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Models\PrimaryGrade;
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

test('la moyenne pondérée et le rang sont calculés correctement par composition (primaire)', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->primaire()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $column = PrimarySubject::coefficientColumn($classroom->level);
    $baremeColumn = PrimarySubject::baremeColumn($classroom->level);
    $subjectA = PrimarySubject::factory()->create([$column => 1, $baremeColumn => 20]);
    $subjectB = PrimarySubject::factory()->create([$column => 1, $baremeColumn => 20]);

    // Une seule évaluation ("composition") couvre toutes les matières et
    // toutes les classes — plus de classroom_id sur GradeSheet.
    $sheet = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => null,
        'subject_id' => null,
        'primary_subject_id' => null,
        'term_id' => null,
        'composition_number' => 1,
    ]);

    // Une composition existante ailleurs (n° 2) ne doit pas être mélangée à la n° 1.
    $otherSheet = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => null,
        'subject_id' => null,
        'primary_subject_id' => null,
        'term_id' => null,
        'composition_number' => 2,
    ]);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $student->id, 'classroom_id' => $classroom->id, 'status' => 'active']);

    PrimaryGrade::factory()->create(['establishment_id' => $establishment->id, 'grade_sheet_id' => $sheet->id, 'student_id' => $student->id, 'primary_subject_id' => $subjectA->id, 'score' => 16]);
    PrimaryGrade::factory()->create(['establishment_id' => $establishment->id, 'grade_sheet_id' => $sheet->id, 'student_id' => $student->id, 'primary_subject_id' => $subjectB->id, 'score' => 10]);
    PrimaryGrade::factory()->create(['establishment_id' => $establishment->id, 'grade_sheet_id' => $otherSheet->id, 'student_id' => $student->id, 'primary_subject_id' => $subjectA->id, 'score' => 2]);

    $reportCards = (new ReportCardService)->generateForClassroomAndComposition($classroom, 1);

    expect($reportCards)->toHaveCount(1);

    $card = $reportCards->first();

    expect((float) $card->average)->toBe(13.0)
        ->and($card->term_id)->toBeNull()
        ->and($card->composition_number)->toBe(1)
        ->and($card->school_year_id)->toBe($schoolYear->id);
});

test('une composition commune à toutes les classes n’agrège que les élèves de la classe demandée', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroomA = Classroom::factory()->primaire()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $classroomB = Classroom::factory()->primaire()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $columnA = PrimarySubject::coefficientColumn($classroomA->level);
    $baremeColumnA = PrimarySubject::baremeColumn($classroomA->level);
    $subject = PrimarySubject::factory()->create([$columnA => 1, $baremeColumnA => 20]);

    // Une seule composition, partagée par les deux classes.
    $sheet = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => null,
        'subject_id' => null,
        'primary_subject_id' => null,
        'term_id' => null,
        'composition_number' => 1,
    ]);

    $studentA = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $studentA->id, 'classroom_id' => $classroomA->id, 'status' => 'active']);
    $studentB = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $studentB->id, 'classroom_id' => $classroomB->id, 'status' => 'active']);

    PrimaryGrade::factory()->create(['establishment_id' => $establishment->id, 'grade_sheet_id' => $sheet->id, 'student_id' => $studentA->id, 'primary_subject_id' => $subject->id, 'score' => 12]);
    PrimaryGrade::factory()->create(['establishment_id' => $establishment->id, 'grade_sheet_id' => $sheet->id, 'student_id' => $studentB->id, 'primary_subject_id' => $subject->id, 'score' => 18]);

    $reportCards = (new ReportCardService)->generateForClassroomAndComposition($classroomA, 1);

    expect($reportCards)->toHaveCount(1)
        ->and($reportCards->first()->student_id)->toBe($studentA->id);
});

test('le détail par matière (primaire) affiche le nom de la matière du catalogue primaire', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->primaire()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $column = PrimarySubject::coefficientColumn($classroom->level);
    $baremeColumn = PrimarySubject::baremeColumn($classroom->level);
    $subject = PrimarySubject::factory()->create(['name' => 'Éveil scientifique', $column => 1, $baremeColumn => 20]);

    $sheet = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => null,
        'subject_id' => null,
        'primary_subject_id' => null,
        'term_id' => null,
        'composition_number' => 1,
    ]);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $student->id, 'classroom_id' => $classroom->id, 'status' => 'active']);
    PrimaryGrade::factory()->create(['establishment_id' => $establishment->id, 'grade_sheet_id' => $sheet->id, 'student_id' => $student->id, 'primary_subject_id' => $subject->id, 'score' => 15]);

    $service = new ReportCardService;
    $reportCard = $service->generateForClassroomAndComposition($classroom, 1)->first();

    $breakdown = $service->subjectBreakdown($reportCard);

    expect($breakdown)->toHaveCount(1)
        ->and($breakdown->first()->subject->name)->toBe('Éveil scientifique')
        ->and($breakdown->first()->average)->toBe(15.0)
        ->and($breakdown->first()->coefficient)->toBe(1.0);
});

test('generate() calcule l’appréciation depuis le barème pour le secondaire', function () {
    AppreciationScale::factory()->create(['percentage' => 70, 'appreciation' => 'Bien']);
    AppreciationScale::factory()->create(['percentage' => 0, 'appreciation' => 'Insuffisant']);

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

    // 14/20 = 70 % → tranche « Bien ».
    $student = makeGradedStudent($establishment, $classroom, $term, [[$sheet, 14]]);

    $reportCard = (new ReportCardService)->generateForClassroomAndTerm($classroom, $term)->firstWhere('student_id', $student->id);

    expect($reportCard->appreciation)->toBe('Bien');
});

test('generate() calcule l’appréciation depuis le barème pour le primaire', function () {
    AppreciationScale::factory()->create(['percentage' => 50, 'appreciation' => 'Passable']);
    AppreciationScale::factory()->create(['percentage' => 0, 'appreciation' => 'Insuffisant']);

    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->primaire()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $column = PrimarySubject::coefficientColumn($classroom->level);
    $baremeColumn = PrimarySubject::baremeColumn($classroom->level);
    $subject = PrimarySubject::factory()->create([$column => 1, $baremeColumn => 20]);

    $sheet = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => null,
        'subject_id' => null,
        'primary_subject_id' => null,
        'term_id' => null,
        'composition_number' => 1,
    ]);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'student_id' => $student->id, 'classroom_id' => $classroom->id, 'status' => 'active']);
    // 10/20 = 50 % → tranche « Passable ».
    PrimaryGrade::factory()->create(['establishment_id' => $establishment->id, 'grade_sheet_id' => $sheet->id, 'student_id' => $student->id, 'primary_subject_id' => $subject->id, 'score' => 10]);

    $reportCard = (new ReportCardService)->generateForClassroomAndComposition($classroom, 1)->first();

    expect($reportCard->appreciation)->toBe('Passable');
});

test('la génération de bulletin est refusée pour une classe préscolaire', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->prescolaire()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $term = Term::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);

    expect(fn () => (new ReportCardService)->generateForClassroomAndTerm($classroom, $term))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});
