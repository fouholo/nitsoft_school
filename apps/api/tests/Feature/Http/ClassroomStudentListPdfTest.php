<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Direction;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Domain\Establishments\Models\Inspection;

test('un membre de l’établissement peut consulter le PDF de la liste des élèves en ligne', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    actingInEstablishment($establishment);
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);

    $response = $this->actingAs($teacher)->get(route('reports.classroom-students-pdf', $classroom));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

test('le paramètre download force le téléchargement du PDF', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);

    $response = $this->actingAs($admin)->get(route('reports.classroom-students-pdf', ['classroom' => $classroom, 'download' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('un membre d’un autre établissement ne peut même pas résoudre la classe (isolation tenant)', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();
    $adminB = createUserWithRole($establishmentB, 'directeur');

    actingInEstablishment($establishmentA);
    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishmentA->id]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishmentA->id, 'school_year_id' => $schoolYear->id]);

    actingInEstablishment($establishmentB);

    $response = $this->actingAs($adminB)->get(route('reports.classroom-students-pdf', $classroom));

    $response->assertNotFound();
});

test('la liste contient les élèves de la classe triés par nom, avec les bonnes colonnes', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $otherClassroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);

    $zed = Student::factory()->create([
        'establishment_id' => $establishment->id,
        'last_name' => 'Zaoui',
        'first_name' => 'Zoé',
        'student_number' => 'MAT-ZZZZ',
        'gender' => 'f',
        'birth_place' => 'Abidjan',
    ]);
    $ade = Student::factory()->create([
        'establishment_id' => $establishment->id,
        'last_name' => 'Adegnon',
        'first_name' => 'Ali',
        'student_number' => 'MAT-AAAA',
        'gender' => 'm',
        'birth_place' => 'Bouaké',
    ]);
    $outsider = Student::factory()->create([
        'establishment_id' => $establishment->id,
        'last_name' => 'Horsclasse',
        'first_name' => 'Ines',
    ]);

    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'classroom_id' => $classroom->id, 'school_year_id' => $schoolYear->id, 'student_id' => $zed->id]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'classroom_id' => $classroom->id, 'school_year_id' => $schoolYear->id, 'student_id' => $ade->id]);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'classroom_id' => $otherClassroom->id, 'school_year_id' => $schoolYear->id, 'student_id' => $outsider->id]);

    $classroom->loadMissing(['level', 'serie', 'schoolYear', 'establishment.inspection.direction']);

    $students = $classroom->enrollments()
        ->where('status', 'active')
        ->with('student')
        ->get()
        ->pluck('student')
        ->sortBy([['last_name', 'asc'], ['first_name', 'asc']])
        ->values();

    $html = view('pdf.classroom-student-list', [
        'classroom' => $classroom,
        'students' => $students,
        'generalInformation' => GeneralInformation::current(),
    ])->render();

    expect($html)->toContain('MAT-AAAA')
        ->and($html)->toContain('MAT-ZZZZ')
        ->and($html)->toContain('Abidjan')
        ->and($html)->toContain('Bouaké')
        ->and($html)->not->toContain('Horsclasse');

    expect(strpos($html, 'MAT-AAAA'))->toBeLessThan(strpos($html, 'MAT-ZZZZ'));
});

function renderClassroomStudentListHtml(Classroom $classroom): string
{
    $classroom->loadMissing(['level', 'serie', 'schoolYear', 'establishment.inspection.direction']);

    return view('pdf.classroom-student-list', [
        'classroom' => $classroom,
        'students' => collect(),
        'generalInformation' => GeneralInformation::current(),
    ])->render();
}

test('l’en-tête complet s’affiche pour un établissement préscolaire/primaire avec ministère, direction, inspection et armoirie renseignés', function () {
    GeneralInformation::current()->update([
        'nom_ministere' => 'Ministère de l’Éducation Nationale',
        'annee_scolaire_courante' => '2025-2026',
        'armoirie_path' => 'general-information/armoirie.png',
    ]);

    $direction = Direction::create(['code' => 'DR-TST', 'direction_name' => 'Test Direction']);
    $inspection = Inspection::create(['codeiep' => 'IEPTST', 'inspection_name' => 'Test Inspection', 'uid_direction' => $direction->uid_serveur]);

    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire, 'inspection_id' => $inspection->id, 'logo_path' => 'establishments-logos/logo.png']);
    actingInEstablishment($establishment);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);

    $html = renderClassroomStudentListHtml($classroom);

    expect($html)->toContain('MINISTÈRE DE L’ÉDUCATION NATIONALE')
        ->and($html)->toContain('DIRECTION REGIONALE TEST DIRECTION')
        ->and($html)->toContain('INSPECTION DE L\'ENSEIGNEMENT PRESCOLAIRE ET PRIMAIRE TEST INSPECTION')
        ->and($html)->toContain('REPUBLIQUE DE CÔTE D\'IVOIRE')
        ->and($html)->toContain('Union-Discipline-Travail')
        ->and($html)->toContain('Année scolaire : <span style="font-weight:bold;">2025-2026</span>')
        ->and(substr_count($html, '<img'))->toBe(2);
});

test('la ligne direction régionale est absente si l’inspection n’a pas de direction rattachée', function () {
    $inspection = Inspection::create(['codeiep' => 'IEPNOD', 'inspection_name' => 'Sans Direction']);
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire, 'inspection_id' => $inspection->id]);
    actingInEstablishment($establishment);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);

    $html = renderClassroomStudentListHtml($classroom);

    expect($html)->not->toContain('DIRECTION REGIONALE');
});

test('la ligne inspection est absente si l’établissement n’a pas d’inspection renseignée', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire, 'inspection_id' => null]);
    actingInEstablishment($establishment);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);

    $html = renderClassroomStudentListHtml($classroom);

    expect($html)->not->toContain('INSPECTION DE');
});

test('la ligne inspection est absente pour un établissement secondaire même avec une inspection renseignée', function () {
    $inspection = Inspection::create(['codeiep' => 'IEPSEC', 'inspection_name' => 'Inspection Secondaire']);
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire, 'inspection_id' => $inspection->id]);
    actingInEstablishment($establishment);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);

    $html = renderClassroomStudentListHtml($classroom);

    expect($html)->not->toContain('INSPECTION DE');
});

test('l’armoirie est absente si elle n’est pas renseignée dans les informations générales', function () {
    GeneralInformation::current()->update(['armoirie_path' => null]);

    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);

    $html = renderClassroomStudentListHtml($classroom);

    expect(substr_count($html, '<img'))->toBe(0);
});

test('le cadre signature affiche "Le directeur" et le nom du directeur de l’établissement', function () {
    $establishment = Establishment::factory()->create();
    $director = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);

    $html = renderClassroomStudentListHtml($classroom);

    expect($html)->toContain('Le directeur')
        ->and($html)->toContain(e($director->name));
});
