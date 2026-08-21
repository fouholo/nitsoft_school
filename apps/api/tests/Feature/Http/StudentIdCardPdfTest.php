<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;

test('un membre de l’établissement peut consulter la carte en ligne', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    actingInEstablishment($establishment);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    $response = $this->actingAs($teacher)->get(route('reports.student-id-card-pdf', $student));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

test('le paramètre download force le téléchargement de la carte', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    $response = $this->actingAs($admin)->get(route('reports.student-id-card-pdf', ['student' => $student, 'download' => 1]));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
});

test('un membre d’un autre établissement ne peut même pas résoudre l’élève (isolation tenant)', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();
    $adminB = createUserWithRole($establishmentB, 'directeur');

    actingInEstablishment($establishmentA);
    $student = Student::factory()->create(['establishment_id' => $establishmentA->id]);

    actingInEstablishment($establishmentB);

    $response = $this->actingAs($adminB)->get(route('reports.student-id-card-pdf', $student));

    $response->assertNotFound();
});

function renderStudentIdCardHtml(Student $student, ?Classroom $classroom = null, ?SchoolYear $schoolYear = null): string
{
    return view('pdf.student-id-card', [
        'student' => $student,
        'establishment' => $student->establishment,
        'classroom' => $classroom,
        'schoolYear' => $schoolYear,
    ])->render();
}

test('la carte affiche l’identité, le matricule, la classe, l’année et l’établissement', function () {
    $establishment = Establishment::factory()->create(['name' => 'Groupe Scolaire Excellence']);
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create(['label' => '2026-2027', 'is_current' => true]);
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $student = Student::factory()->create([
        'establishment_id' => $establishment->id,
        'last_name' => 'Traoré',
        'first_name' => 'Awa',
        'student_number' => 'MAT-0099',
        'birth_date' => '2015-03-12',
        'birth_place' => 'Abidjan',
    ]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $schoolYear->id,
        'student_id' => $student->id,
        'status' => 'active',
    ]);

    $html = renderStudentIdCardHtml($student, $classroom, $schoolYear);

    expect($html)->toContain('TRAORÉ')
        ->and($html)->toContain('Awa')
        ->and($html)->toContain('MAT-0099')
        ->and($html)->toContain($classroom->name)
        ->and($html)->toContain('2026-2027')
        ->and($html)->toContain('12/03/2015')
        ->and($html)->toContain('Abidjan')
        ->and($html)->toContain('GROUPE SCOLAIRE EXCELLENCE');
});

test('sans inscription active, la classe est affichée comme non renseignée plutôt que de planter', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    $html = renderStudentIdCardHtml($student);

    expect($html)->toContain('—');
});

test('sans photo, un espace réservé s’affiche plutôt qu’une image cassée', function () {
    $establishment = Establishment::factory()->create(['logo_path' => null]);
    actingInEstablishment($establishment);
    $student = Student::factory()->create(['establishment_id' => $establishment->id, 'photo_path' => null]);

    $html = renderStudentIdCardHtml($student);

    expect($html)->toContain('Photo')
        ->and(substr_count($html, '<img'))->toBe(0);
});
