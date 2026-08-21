<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\SubjectCoefficient;
use App\Domain\Academics\Models\Term;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\Grade;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Models\ReportCard;
use App\Livewire\Grading\ReportCards\Secondaire\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $this->admin = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $schoolYear = SchoolYear::factory()->create();
    $this->term = Term::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $schoolYear->id]);
});

test('la période est requise', function () {
    $classroom = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->term->school_year_id,
    ]);

    Livewire::test(Index::class)
        ->set('classroom_id', $classroom->id)
        ->call('generate')
        ->assertHasErrors(['term_id'])
        ->assertSee('obligatoire');

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
        ->assertHasErrors(['classroom_id'])
        ->assertSee('Coefficient manquant pour');

    expect(ReportCard::count())->toBe(0);
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
        ->and($reportCard->rank)->toBe(1)
        ->and($reportCard->composition_number)->toBeNull();
});

test('le résumé signale les élèves exclus faute de note et affiche la date de génération', function () {
    $classroom = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->term->school_year_id,
    ]);
    $subject = Subject::factory()->create();
    $gradedStudent = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    $ungradedStudent = Student::factory()->create(['establishment_id' => $this->establishment->id]);

    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $gradedStudent->id,
        'classroom_id' => $classroom->id,
        'status' => 'active',
    ]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $ungradedStudent->id,
        'classroom_id' => $classroom->id,
        'status' => 'active',
    ]);

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
        'term_id' => $this->term->id,
    ]);
    Grade::factory()->create([
        'establishment_id' => $this->establishment->id,
        'grade_sheet_id' => $gradeSheet->id,
        'student_id' => $gradedStudent->id,
        'score' => 14,
    ]);

    $component = Livewire::test(Index::class)
        ->set('classroom_id', $classroom->id)
        ->set('term_id', $this->term->id)
        ->assertSee('2 élève')
        ->assertSee('Bulletins non encore générés')
        ->assertSee('Générer les bulletins')
        ->assertDontSee('Régénérer les bulletins');

    $component->call('generate')
        ->assertHasNoErrors()
        ->assertSee('1/2 élèves classés')
        ->assertSee('1 élève(s) sans note exclu(s) du classement')
        ->assertSee('Régénérer les bulletins');
});
