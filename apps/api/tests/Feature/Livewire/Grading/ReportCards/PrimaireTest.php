<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Models\PrimaryGrade;
use App\Domain\Grading\Models\ReportCard;
use App\Livewire\Grading\ReportCards\Primaire\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $this->admin = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $this->schoolYear = SchoolYear::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->prescolaireClassroom = Classroom::factory()->prescolaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
});

test('la classe préscolaire (non notée) est absente du sélecteur de classe', function () {
    $classrooms = Livewire::test(Index::class)->viewData('classrooms');

    expect($classrooms->pluck('id'))->not->toContain($this->prescolaireClassroom->id);
});

test('la génération est bloquée si le coefficient d’une matière notée n’est pas configuré', function () {
    $classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    $column = PrimarySubject::coefficientColumn($classroom->level);
    $subject = PrimarySubject::factory()->create([$column => null]);
    $student = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create(['establishment_id' => $this->establishment->id, 'student_id' => $student->id, 'classroom_id' => $classroom->id, 'status' => 'active']);

    $gradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => null,
        'subject_id' => null,
        'primary_subject_id' => null,
        'term_id' => null,
        'composition_number' => 1,
    ]);
    PrimaryGrade::factory()->create([
        'establishment_id' => $this->establishment->id,
        'grade_sheet_id' => $gradeSheet->id,
        'student_id' => $student->id,
        'primary_subject_id' => $subject->id,
        'score' => 12,
    ]);

    Livewire::test(Index::class)
        ->set('classroom_id', $classroom->id)
        ->set('composition_number', 1)
        ->call('generate')
        ->assertHasErrors(['classroom_id']);

    expect(ReportCard::count())->toBe(0);
});

test('la génération de bulletin par composition agrège plusieurs matières sous une seule évaluation', function () {
    $classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    $column = PrimarySubject::coefficientColumn($classroom->level);
    $baremeColumn = PrimarySubject::baremeColumn($classroom->level);
    $maths = PrimarySubject::factory()->create([$column => 4, $baremeColumn => 20]);
    $francais = PrimarySubject::factory()->create([$column => 1, $baremeColumn => 10]);
    $student = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create(['establishment_id' => $this->establishment->id, 'student_id' => $student->id, 'classroom_id' => $classroom->id, 'status' => 'active']);

    $gradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => null,
        'subject_id' => null,
        'primary_subject_id' => null,
        'term_id' => null,
        'composition_number' => 1,
    ]);
    PrimaryGrade::factory()->create([
        'establishment_id' => $this->establishment->id,
        'grade_sheet_id' => $gradeSheet->id,
        'student_id' => $student->id,
        'primary_subject_id' => $maths->id,
        'score' => 16,
    ]);
    PrimaryGrade::factory()->create([
        'establishment_id' => $this->establishment->id,
        'grade_sheet_id' => $gradeSheet->id,
        'student_id' => $student->id,
        'primary_subject_id' => $francais->id,
        'score' => 8,
    ]);

    Livewire::test(Index::class)
        ->set('classroom_id', $classroom->id)
        ->set('composition_number', 1)
        ->call('generate')
        ->assertHasNoErrors();

    $reportCard = ReportCard::sole();

    // Maths 16/20 coef 4 → 16 ; Français 8/10 coef 1 → 16. Moyenne = 16.
    expect((float) $reportCard->average)->toBe(16.0)
        ->and($reportCard->term_id)->toBeNull()
        ->and($reportCard->composition_number)->toBe(1)
        ->and($reportCard->school_year_id)->toBe($classroom->school_year_id);
});

test('le n° de composition est requis', function () {
    $classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    Livewire::test(Index::class)
        ->set('classroom_id', $classroom->id)
        ->call('generate')
        ->assertHasErrors(['composition_number']);

    expect(ReportCard::count())->toBe(0);
});

test('un bulletin non encore officiellement généré (appréciation seule) n’apparaît pas dans la liste', function () {
    $classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    $student = Student::factory()->create(['establishment_id' => $this->establishment->id]);

    ReportCard::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'school_year_id' => $this->schoolYear->id,
        'term_id' => null,
        'composition_number' => 1,
        'average' => null,
        'rank' => null,
        'generated_at' => null,
        'appreciation' => 'Saisie en cours',
    ]);

    $reportCards = Livewire::test(Index::class)
        ->set('classroom_id', $classroom->id)
        ->set('composition_number', 1)
        ->viewData('reportCards');

    expect($reportCards)->toHaveCount(0);
});
