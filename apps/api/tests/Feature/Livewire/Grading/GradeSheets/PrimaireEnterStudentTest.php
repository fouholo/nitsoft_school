<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Models\PrimaryGrade;
use App\Domain\Grading\Models\ReportCard;
use App\Domain\Grading\Services\ReportCardService;
use App\Livewire\Grading\GradeSheets\Primaire\EnterStudent;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $this->admin = createUserWithRole($this->establishment, 'educateur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $this->schoolYear = SchoolYear::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    $this->gradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => null,
        'subject_id' => null,
        'primary_subject_id' => null,
        'term_id' => null,
        'composition_number' => 1,
        'type' => 'composition',
    ]);
    $this->student = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $this->student->id,
        'classroom_id' => $this->classroom->id,
        'status' => 'active',
    ]);

    $column = PrimarySubject::coefficientColumn($this->classroom->level);
    $baremeColumn = PrimarySubject::baremeColumn($this->classroom->level);
    $this->maths = PrimarySubject::factory()->create(['name' => 'Mathématiques', $column => 4, $baremeColumn => 20]);
    $this->francais = PrimarySubject::factory()->create(['name' => 'Français', $column => 1, $baremeColumn => 10]);
});

test('les notes de plusieurs matières sont enregistrées pour l’élève sous une même composition', function () {
    Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->maths->id}", '16')
        ->set("scores.{$this->francais->id}", '8')
        ->set('appreciation', 'Bon trimestre')
        ->call('save')
        ->assertHasNoErrors();

    expect(PrimaryGrade::count())->toBe(2);

    $mathsGrade = PrimaryGrade::where('primary_subject_id', $this->maths->id)->sole();
    $francaisGrade = PrimaryGrade::where('primary_subject_id', $this->francais->id)->sole();

    expect((float) $mathsGrade->score)->toBe(16.0)
        ->and($mathsGrade->grade_sheet_id)->toBe($this->gradeSheet->id)
        ->and((float) $francaisGrade->score)->toBe(8.0);
});

test('l’appréciation est enregistrée sur le ReportCard sans moyenne officielle', function () {
    Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->maths->id}", '16')
        ->set('appreciation', 'Bon trimestre')
        ->call('save')
        ->assertHasNoErrors();

    $reportCard = ReportCard::sole();

    expect($reportCard->appreciation)->toBe('Bon trimestre')
        ->and($reportCard->average)->toBeNull()
        ->and($reportCard->rank)->toBeNull();
});

test('générer officiellement les bulletins après la saisie ne perd pas l’appréciation', function () {
    Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->maths->id}", '16')
        ->set("scores.{$this->francais->id}", '8')
        ->set('appreciation', 'Bon trimestre')
        ->call('save');

    (new ReportCardService)->generateForClassroomAndComposition($this->classroom, 1);

    $reportCard = ReportCard::sole();

    expect($reportCard->appreciation)->toBe('Bon trimestre')
        ->and($reportCard->average)->not->toBeNull();
});

test('les notes déjà saisies sont préchargées à l’ouverture de l’écran', function () {
    PrimaryGrade::factory()->create([
        'establishment_id' => $this->establishment->id,
        'grade_sheet_id' => $this->gradeSheet->id,
        'student_id' => $this->student->id,
        'primary_subject_id' => $this->maths->id,
        'score' => 12,
    ]);
    ReportCard::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $this->student->id,
        'classroom_id' => $this->classroom->id,
        'school_year_id' => $this->schoolYear->id,
        'term_id' => null,
        'composition_number' => 1,
        'appreciation' => 'Peut mieux faire',
    ]);

    Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->assertSet("scores.{$this->maths->id}", '12.00')
        ->assertSet('appreciation', 'Peut mieux faire');
});

test('l’aperçu en direct calcule la moyenne pondérée par coefficient et barème', function () {
    // Maths : 16/20, coef 4 → normalisé 16 ; Français : 8/10, coef 1 → normalisé 16.
    // Moyenne = (16*4 + 16*1) / (4+1) = 16.
    $component = Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->maths->id}", '16')
        ->set("scores.{$this->francais->id}", '8');

    $preview = $component->viewData('preview');

    expect($preview['average'])->toBe(16.0);
});

test('un enseignant sans affectation sur la classe est refusé', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->assertForbidden();
});

test('un enseignant affecté à la classe de l’élève peut noter même s’il n’a pas créé la composition', function () {
    // La composition a été créée par $this->admin (educateur) dans le
    // beforeEach de GradeSheet::factory() — un autre enseignant, simplement
    // affecté à la classe de l'élève, doit pouvoir y saisir des notes : la
    // composition est commune à toutes les classes, l'autorisation ne
    // dépend plus de qui l'a créée.
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    TeacherAssignment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $teacher->id,
        'classroom_id' => $this->classroom->id,
        'subject_id' => null,
    ]);
    $this->actingAs($teacher);

    Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->maths->id}", '15')
        ->call('save')
        ->assertHasNoErrors();

    expect(PrimaryGrade::where('primary_subject_id', $this->maths->id)->sole()->score)->toEqualWithDelta(15.0, 0.001);
});
