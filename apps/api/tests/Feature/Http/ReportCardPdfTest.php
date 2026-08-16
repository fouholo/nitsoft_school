<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Term;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\ReportCard;
use App\Domain\Grading\Services\ReportCardService;

function makeReportCardIn(Establishment $establishment): ReportCard
{
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $term = Term::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    return ReportCard::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'term_id' => $term->id,
        'student_id' => $student->id,
    ]);
}

test('un membre de l’établissement peut consulter le PDF du bulletin en ligne', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);
    $reportCard = makeReportCardIn($establishment);

    $response = $this->actingAs($admin)->get(route('grading.report-cards.pdf', $reportCard));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

test('le paramètre download force le téléchargement du PDF', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);
    $reportCard = makeReportCardIn($establishment);

    $response = $this->actingAs($admin)->get(route('grading.report-cards.pdf', ['reportCard' => $reportCard, 'download' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('un membre d’un autre établissement ne peut même pas résoudre le bulletin (isolation tenant)', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();
    $adminB = createUserWithRole($establishmentB, 'directeur');

    actingInEstablishment($establishmentA);
    $reportCard = makeReportCardIn($establishmentA);

    actingInEstablishment($establishmentB);

    // Le Global Scope tenant masque déjà le bulletin d'un autre établissement
    // au niveau du binding de route (404), avant même que la Policy ne soit
    // évaluée — défense en profondeur, cf. plan d'architecture section 2.1.
    $response = $this->actingAs($adminB)->get(route('grading.report-cards.pdf', $reportCard));

    $response->assertNotFound();
});

test('le logo de l’établissement s’affiche en en-tête du bulletin quand renseigné', function () {
    $establishment = Establishment::factory()->create(['logo_path' => 'establishments-logos/logo.png']);
    actingInEstablishment($establishment);
    $reportCard = makeReportCardIn($establishment);
    $reportCard->loadMissing(['student', 'classroom', 'term.schoolYear', 'establishment']);

    $html = view('pdf.report-card', [
        'reportCard' => $reportCard,
        'breakdown' => app(ReportCardService::class)->subjectBreakdown($reportCard),
    ])->render();

    expect($html)->toContain('<img');
});

test('aucun logo affiché en en-tête du bulletin quand l’établissement n’en a pas', function () {
    $establishment = Establishment::factory()->create(['logo_path' => null]);
    actingInEstablishment($establishment);
    $reportCard = makeReportCardIn($establishment);
    $reportCard->loadMissing(['student', 'classroom', 'term.schoolYear', 'establishment']);

    $html = view('pdf.report-card', [
        'reportCard' => $reportCard,
        'breakdown' => app(ReportCardService::class)->subjectBreakdown($reportCard),
    ])->render();

    expect($html)->not->toContain('<img');
});

test('le PDF d’un bulletin primaire affiche "Composition N" au lieu d’une période', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->primaire()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    actingInEstablishment($establishment);

    $reportCard = ReportCard::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'term_id' => null,
        'school_year_id' => $schoolYear->id,
        'composition_number' => 2,
        'student_id' => $student->id,
    ]);
    $reportCard->loadMissing(['student', 'classroom', 'term.schoolYear', 'schoolYear', 'establishment']);

    $html = view('pdf.report-card', [
        'reportCard' => $reportCard,
        'breakdown' => app(ReportCardService::class)->subjectBreakdown($reportCard),
    ])->render();

    expect($html)->toContain('Composition 2')
        ->and($html)->toContain($schoolYear->label)
        ->and($html)->not->toContain('Bulletin —  (');
});

test('l’appréciation s’affiche sur le bulletin quand elle est renseignée', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $reportCard = makeReportCardIn($establishment);
    $reportCard->update(['appreciation' => 'Élève sérieux et appliqué.']);
    $reportCard->loadMissing(['student', 'classroom', 'term.schoolYear', 'establishment']);

    $html = view('pdf.report-card', [
        'reportCard' => $reportCard,
        'breakdown' => app(ReportCardService::class)->subjectBreakdown($reportCard),
    ])->render();

    expect($html)->toContain('Élève sérieux et appliqué.');
});

test('aucune ligne appréciation quand elle n’est pas renseignée', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $reportCard = makeReportCardIn($establishment);
    $reportCard->loadMissing(['student', 'classroom', 'term.schoolYear', 'establishment']);

    $html = view('pdf.report-card', [
        'reportCard' => $reportCard,
        'breakdown' => app(ReportCardService::class)->subjectBreakdown($reportCard),
    ])->render();

    expect($html)->not->toContain('Appréciation');
});

test('le cadre signature affiche "Le directeur" et le nom du directeur de l’établissement', function () {
    $establishment = Establishment::factory()->create();
    $director = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $reportCard = makeReportCardIn($establishment);
    $reportCard->loadMissing(['student', 'classroom', 'term.schoolYear', 'establishment']);

    $html = view('pdf.report-card', [
        'reportCard' => $reportCard,
        'breakdown' => app(ReportCardService::class)->subjectBreakdown($reportCard),
    ])->render();

    expect($html)->toContain('Le directeur')
        ->and($html)->toContain(e($director->name));
});
