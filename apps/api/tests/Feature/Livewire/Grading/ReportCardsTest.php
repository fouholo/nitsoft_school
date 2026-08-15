<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\SubjectCoefficient;
use App\Domain\Academics\Models\Term;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\Grade;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Models\ReportCard;
use App\Livewire\Grading\ReportCards\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->admin = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->prescolaireClassroom = Classroom::factory()->prescolaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);
    $this->term = Term::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $schoolYear->id]);
});

test('la classe préscolaire est absente du sélecteur de classe', function () {
    $classrooms = Livewire::test(Index::class)->viewData('classrooms');

    expect($classrooms->pluck('id'))->not->toContain($this->prescolaireClassroom->id);
});

test('la génération de bulletin pour une classe préscolaire est refusée', function () {
    Livewire::test(Index::class)
        ->set('classroom_id', $this->prescolaireClassroom->id)
        ->set('term_id', $this->term->id)
        ->call('generate')
        ->assertHasErrors(['classroom_id']);

    expect(ReportCard::count())->toBe(0);
});

test('la génération est bloquée si le coefficient d’une matière notée n’est pas configuré', function () {
    $classroom = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->term->school_year_id,
    ]);
    $subject = Subject::factory()->create();
    $student = Student::factory()->create(['establishment_id' => $this->establishment->id]);

    $gradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'term_id' => $this->term->id,
    ]);
    Grade::factory()->create([
        'establishment_id' => $this->establishment->id,
        'grade_sheet_id' => $gradeSheet->id,
        'student_id' => $student->id,
        'score' => 12,
    ]);

    Livewire::test(Index::class)
        ->set('classroom_id', $classroom->id)
        ->set('term_id', $this->term->id)
        ->call('generate')
        ->assertHasErrors(['classroom_id']);

    expect(ReportCard::count())->toBe(0);
});

test('la génération de bulletin par composition (primaire) fonctionne et rejette une période', function () {
    $classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->term->school_year_id,
    ]);
    $subject = Subject::factory()->create();
    $student = Student::factory()->create(['establishment_id' => $this->establishment->id]);

    SubjectCoefficient::factory()->create([
        'establishment_id' => $this->establishment->id,
        'level_id' => $classroom->level_id,
        'serie_id' => null,
        'subject_id' => $subject->id,
        'coefficient' => 1,
    ]);

    $gradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
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
        ->set('term_id', $this->term->id)
        ->call('generate')
        ->assertHasErrors(['term_id', 'composition_number']);

    expect(ReportCard::count())->toBe(0);

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

test('la moyenne générale pondère chaque matière par son coefficient', function () {
    $classroom = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->term->school_year_id,
    ]);
    $subjectA = Subject::factory()->create();
    $subjectB = Subject::factory()->create();
    $student = Student::factory()->create(['establishment_id' => $this->establishment->id]);

    SubjectCoefficient::factory()->create([
        'establishment_id' => $this->establishment->id,
        'level_id' => $classroom->level_id,
        'serie_id' => null,
        'subject_id' => $subjectA->id,
        'coefficient' => 4,
    ]);
    SubjectCoefficient::factory()->create([
        'establishment_id' => $this->establishment->id,
        'level_id' => $classroom->level_id,
        'serie_id' => null,
        'subject_id' => $subjectB->id,
        'coefficient' => 1,
    ]);

    $gradeSheetA = GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subjectA->id,
        'term_id' => $this->term->id,
        'weight' => 1,
        'max_score' => 20,
    ]);
    $gradeSheetB = GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subjectB->id,
        'term_id' => $this->term->id,
        'weight' => 1,
        'max_score' => 20,
    ]);
    Grade::factory()->create([
        'establishment_id' => $this->establishment->id,
        'grade_sheet_id' => $gradeSheetA->id,
        'student_id' => $student->id,
        'score' => 10,
    ]);
    Grade::factory()->create([
        'establishment_id' => $this->establishment->id,
        'grade_sheet_id' => $gradeSheetB->id,
        'student_id' => $student->id,
        'score' => 16,
    ]);

    Livewire::test(Index::class)
        ->set('classroom_id', $classroom->id)
        ->set('term_id', $this->term->id)
        ->call('generate')
        ->assertHasNoErrors();

    $reportCard = ReportCard::sole();

    // (10×4 + 16×1) / (4+1) = 11.2
    expect((float) $reportCard->average)->toBe(11.2)
        ->and($reportCard->rank)->toBe(1);
});
