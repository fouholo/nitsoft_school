<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;

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

    $classroom->loadMissing(['level', 'serie', 'schoolYear', 'establishment']);

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
    ])->render();

    expect($html)->toContain('MAT-AAAA')
        ->and($html)->toContain('MAT-ZZZZ')
        ->and($html)->toContain('Abidjan')
        ->and($html)->toContain('Bouaké')
        ->and($html)->not->toContain('Horsclasse');

    expect(strpos($html, 'MAT-AAAA'))->toBeLessThan(strpos($html, 'MAT-ZZZZ'));
});
