<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;

test('un membre de l’établissement peut consulter la planche de cartes en ligne', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    actingInEstablishment($establishment);
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);

    $response = $this->actingAs($teacher)->get(route('reports.classroom-id-cards-pdf', $classroom));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

test('le paramètre download force le téléchargement de la planche', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);

    $response = $this->actingAs($admin)->get(route('reports.classroom-id-cards-pdf', ['classroom' => $classroom, 'download' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('un membre d’un autre établissement ne peut même pas résoudre la classe (isolation tenant)', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();
    $adminB = createUserWithRole($establishmentB, 'directeur');

    actingInEstablishment($establishmentA);
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishmentA->id, 'school_year_id' => $schoolYear->id]);

    actingInEstablishment($establishmentB);

    $response = $this->actingAs($adminB)->get(route('reports.classroom-id-cards-pdf', $classroom));

    $response->assertNotFound();
});

function renderClassroomIdCardsHtml(Classroom $classroom, $students, ?SchoolYear $schoolYear = null): string
{
    $classroom->loadMissing('establishment');

    return view('pdf.classroom-id-cards', [
        'classroom' => $classroom,
        'students' => $students,
        'schoolYear' => $schoolYear,
    ])->render();
}

test('la planche contient une carte par élève actif, triée par nom, et exclut les autres classes', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['label' => '2026-2027']);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $otherClassroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);

    $zed = Student::factory()->create(['establishment_id' => $establishment->id, 'last_name' => 'Zaoui', 'first_name' => 'Zoé', 'student_number' => 'MAT-ZZZZ']);
    $ade = Student::factory()->create(['establishment_id' => $establishment->id, 'last_name' => 'Adegnon', 'first_name' => 'Ali', 'student_number' => 'MAT-AAAA']);
    $outsider = Student::factory()->create(['establishment_id' => $establishment->id, 'last_name' => 'Horsclasse', 'first_name' => 'Ines', 'student_number' => 'MAT-HORS']);

    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'classroom_id' => $classroom->id, 'school_year_id' => $schoolYear->id, 'student_id' => $zed->id, 'status' => 'active']);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'classroom_id' => $classroom->id, 'school_year_id' => $schoolYear->id, 'student_id' => $ade->id, 'status' => 'active']);
    Enrollment::factory()->create(['establishment_id' => $establishment->id, 'classroom_id' => $otherClassroom->id, 'school_year_id' => $schoolYear->id, 'student_id' => $outsider->id, 'status' => 'active']);

    $students = $classroom->enrollments()
        ->where('status', 'active')
        ->with('student')
        ->get()
        ->pluck('student')
        ->sortBy([['last_name', 'asc'], ['first_name', 'asc']])
        ->values();

    $html = renderClassroomIdCardsHtml($classroom, $students, $schoolYear);

    expect($html)->toContain('MAT-AAAA')
        ->and($html)->toContain('MAT-ZZZZ')
        ->and($html)->not->toContain('MAT-HORS');

    expect(strpos($html, 'MAT-AAAA'))->toBeLessThan(strpos($html, 'MAT-ZZZZ'));
});

test('une classe sans élève actif affiche un message plutôt qu’une planche vide', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);

    $html = renderClassroomIdCardsHtml($classroom, collect());

    expect($html)->toContain('Aucun élève inscrit dans cette classe.');
});
