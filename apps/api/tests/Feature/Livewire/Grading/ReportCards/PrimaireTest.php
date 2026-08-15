<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\Grade;
use App\Domain\Grading\Models\GradeSheet;
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

    $gradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => null,
        'primary_subject_id' => $subject->id,
        'term_id' => null,
        'composition_number' => 1,
    ]);
    Grade::factory()->create([
        'establishment_id' => $this->establishment->id,
        'grade_sheet_id' => $gradeSheet->id,
        'student_id' => $student->id,
        'score' => 12,
    ]);

    Livewire::test(Index::class)
        ->set('classroom_id', $classroom->id)
        ->set('composition_number', 1)
        ->call('generate')
        ->assertHasErrors(['classroom_id']);

    expect(ReportCard::count())->toBe(0);
});

test('la génération de bulletin par composition fonctionne', function () {
    $classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    $column = PrimarySubject::coefficientColumn($classroom->level);
    $subject = PrimarySubject::factory()->create([$column => 1]);
    $student = Student::factory()->create(['establishment_id' => $this->establishment->id]);

    $gradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => null,
        'primary_subject_id' => $subject->id,
        'term_id' => null,
        'composition_number' => 1,
        'weight' => 1,
        'max_score' => 20,
    ]);
    Grade::factory()->create([
        'establishment_id' => $this->establishment->id,
        'grade_sheet_id' => $gradeSheet->id,
        'student_id' => $student->id,
        'score' => 14,
    ]);

    Livewire::test(Index::class)
        ->set('classroom_id', $classroom->id)
        ->set('composition_number', 1)
        ->call('generate')
        ->assertHasNoErrors();

    $reportCard = ReportCard::sole();

    expect((float) $reportCard->average)->toBe(14.0)
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
