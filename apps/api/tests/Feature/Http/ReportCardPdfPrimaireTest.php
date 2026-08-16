<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\AppreciationScale;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Models\PrimaryGrade;
use App\Domain\Grading\Models\ReportCard;
use App\Domain\Grading\Services\ReportCardService;
use Illuminate\Support\Str;

function makePrimaireReportCard(Establishment $establishment, array $studentOverrides = [], ?string $level = 'CM2'): ReportCard
{
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->primaireLevel($level)->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);
    $student = Student::factory()->create(array_merge(['establishment_id' => $establishment->id], $studentOverrides));
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'status' => 'active',
    ]);

    $column = PrimarySubject::coefficientColumn($classroom->level);
    $baremeColumn = PrimarySubject::baremeColumn($classroom->level);
    $maths = PrimarySubject::factory()->create(['name' => 'Mathématiques', $column => 2.5, $baremeColumn => 50]);
    $francais = PrimarySubject::factory()->create(['name' => 'Dictée', $column => 1, $baremeColumn => 20]);

    $gradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => null,
        'subject_id' => null,
        'primary_subject_id' => null,
        'term_id' => null,
        'composition_number' => 1,
    ]);
    PrimaryGrade::factory()->create([
        'establishment_id' => $establishment->id,
        'grade_sheet_id' => $gradeSheet->id,
        'student_id' => $student->id,
        'primary_subject_id' => $maths->id,
        'score' => 46,
    ]);
    PrimaryGrade::factory()->create([
        'establishment_id' => $establishment->id,
        'grade_sheet_id' => $gradeSheet->id,
        'student_id' => $student->id,
        'primary_subject_id' => $francais->id,
        'score' => 16,
    ]);

    return (new ReportCardService)->generateForClassroomAndComposition($classroom, 1)->first();
}

beforeEach(function () {
    AppreciationScale::factory()->create(['percentage' => 90, 'appreciation' => 'Excellent']);
    AppreciationScale::factory()->create(['percentage' => 80, 'appreciation' => 'Très bien']);
    AppreciationScale::factory()->create(['percentage' => 70, 'appreciation' => 'Bien']);
    AppreciationScale::factory()->create(['percentage' => 0, 'appreciation' => 'Insuffisant']);
});

test('un bulletin primaire est rendu via le gabarit A5 dédié, un bulletin secondaire garde le gabarit A4', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $primaireCard = makePrimaireReportCard($establishment);

    $response = $this->actingAs($admin)->get(route('grading.report-cards.pdf', $primaireCard));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('l’en-tête n’affiche pas de texte République, seulement le logo à gauche et l’armoirie à droite', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $reportCard = makePrimaireReportCard($establishment);
    $reportCard->loadMissing(['student', 'classroom.level', 'establishment']);

    $generalInformation = \App\Domain\Establishments\Models\GeneralInformation::current();
    $generalInformation->update(['armoirie_path' => 'general-information/armoirie.png']);

    $html = view('pdf.report-card-primaire', [
        'reportCard' => $reportCard,
        'rows' => app(ReportCardService::class)->primaryGradeRows($reportCard),
        'generalInformation' => $generalInformation,
    ])->render();

    expect($html)->not->toContain('REPUBLIQUE DE')
        ->and($html)->not->toContain('Union-Discipline-Travail')
        ->and($html)->toContain('td class="logo-col"')
        ->and($html)->toContain('td class="left"')
        ->and($html)->toContain('td class="right"')
        ->and($html)->toContain('class="armoirie"')
        ->and($html)->toContain('class="establishment-name"');
});

test('la civilité et le résultat s’accordent au genre de l’élève', function (?string $gender, string $expectedCivilite, string $expectedResultat) {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $reportCard = makePrimaireReportCard($establishment, ['gender' => $gender]);
    $reportCard->loadMissing(['student', 'classroom.level', 'establishment']);

    $html = view('pdf.report-card-primaire', [
        'reportCard' => $reportCard,
        'rows' => app(ReportCardService::class)->primaryGradeRows($reportCard),
        'generalInformation' => \App\Domain\Establishments\Models\GeneralInformation::current(),
    ])->render();

    expect($html)->toContain(e($expectedCivilite))
        ->and($html)->toContain($expectedResultat);
})->with([
    'féminin' => ['f', 'Mademoiselle', 'Admise'],
    'masculin' => ['m', 'Monsieur', 'Admis'],
    'non renseigné' => [null, "L'élève", 'Admis(e)'],
]);

test('le tableau des notes affiche la note/barème brut et une appréciation par matière, sans pondération du total par coefficient', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $reportCard = makePrimaireReportCard($establishment);
    $reportCard->loadMissing(['student', 'classroom.level', 'establishment']);

    $html = view('pdf.report-card-primaire', [
        'reportCard' => $reportCard,
        'rows' => app(ReportCardService::class)->primaryGradeRows($reportCard),
        'generalInformation' => \App\Domain\Establishments\Models\GeneralInformation::current(),
    ])->render();

    // Mathématiques 46/50 (92 % → Excellent), Dictée 16/20 (80 % → Très bien).
    expect($html)->toContain('46 / 50')
        ->and($html)->toContain('16 / 20')
        ->and($html)->toContain('Excellent')
        ->and($html)->toContain('Très bien')
        // Total non pondéré : 46+16=62 sur 50+20=70, coefficients 2.5+1=3.5.
        ->and($html)->toContain('62 / 70')
        ->and($html)->toContain('3.5');
});

test('le visa maître(sse) est vide quand aucun enseignant n’est affecté à la classe entière', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $reportCard = makePrimaireReportCard($establishment);
    $reportCard->loadMissing(['student', 'classroom.level', 'establishment']);

    $html = view('pdf.report-card-primaire', [
        'reportCard' => $reportCard,
        'rows' => app(ReportCardService::class)->primaryGradeRows($reportCard),
        'generalInformation' => \App\Domain\Establishments\Models\GeneralInformation::current(),
    ])->render();

    expect($html)->toContain('Visa maître(sse)');
    expect(trim(Str::before(Str::after($html, '<p class="label">Visa maître(sse)</p>'), '</td>')))
        ->toBe('<p class="name"></p>');
});

test('le visa maître(sse) affiche l’enseignant affecté à la classe entière', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $reportCard = makePrimaireReportCard($establishment);
    $reportCard->loadMissing(['student', 'classroom.level', 'establishment']);

    $teacher = createUserWithRole($establishment, 'enseignant');
    TeacherAssignment::factory()->create([
        'establishment_id' => $establishment->id,
        'user_id' => $teacher->id,
        'classroom_id' => $reportCard->classroom_id,
        'subject_id' => null,
    ]);

    $html = view('pdf.report-card-primaire', [
        'reportCard' => $reportCard,
        'rows' => app(ReportCardService::class)->primaryGradeRows($reportCard),
        'generalInformation' => \App\Domain\Establishments\Models\GeneralInformation::current(),
    ])->render();

    expect($html)->toContain(e($teacher->name));
});

test('le visa directeur(trice) affiche le nom du directeur de l’établissement', function () {
    $establishment = Establishment::factory()->create();
    $director = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $reportCard = makePrimaireReportCard($establishment);
    $reportCard->loadMissing(['student', 'classroom.level', 'establishment']);

    $html = view('pdf.report-card-primaire', [
        'reportCard' => $reportCard,
        'rows' => app(ReportCardService::class)->primaryGradeRows($reportCard),
        'generalInformation' => \App\Domain\Establishments\Models\GeneralInformation::current(),
    ])->render();

    expect($html)->toContain(e($director->name));
});
