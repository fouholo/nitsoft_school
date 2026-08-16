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
use App\Domain\Grading\Models\AppreciationScale;
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
    // Niveau fixé à CM2 (échelle /20) pour que les assertions de moyenne de
    // ce fichier soient déterministes — voir Level::compositionAverageScale().
    $this->classroom = Classroom::factory()->primaireLevel('CM2')->create([
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

    AppreciationScale::factory()->create(['percentage' => 80, 'appreciation' => 'Très bien']);
    AppreciationScale::factory()->create(['percentage' => 0, 'appreciation' => 'Insuffisant']);
});

test('les notes de plusieurs matières sont enregistrées pour l’élève sous une même composition', function () {
    Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->maths->id}", '16')
        ->set("scores.{$this->francais->id}", '8')
        ->call('save')
        ->assertHasNoErrors();

    expect(PrimaryGrade::count())->toBe(2);

    $mathsGrade = PrimaryGrade::where('primary_subject_id', $this->maths->id)->sole();
    $francaisGrade = PrimaryGrade::where('primary_subject_id', $this->francais->id)->sole();

    expect((float) $mathsGrade->score)->toBe(16.0)
        ->and($mathsGrade->grade_sheet_id)->toBe($this->gradeSheet->id)
        ->and((float) $francaisGrade->score)->toBe(8.0);
});

test('l’appréciation est calculée depuis le barème et enregistrée sur le ReportCard sans moyenne officielle', function () {
    // Maths seule : 16/20, coef 4 → moyenne 16 → 80 % → « Très bien ».
    Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->maths->id}", '16')
        ->call('save')
        ->assertHasNoErrors();

    $reportCard = ReportCard::sole();

    expect($reportCard->appreciation)->toBe('Très bien')
        ->and($reportCard->average)->toBeNull()
        ->and($reportCard->rank)->toBeNull();
});

test('générer officiellement les bulletins après la saisie recalcule la même appréciation', function () {
    Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->maths->id}", '16')
        ->set("scores.{$this->francais->id}", '8')
        ->call('save');

    (new ReportCardService)->generateForClassroomAndComposition($this->classroom, 1);

    $reportCard = ReportCard::sole();

    expect($reportCard->appreciation)->toBe('Très bien')
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
    Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->assertSet("scores.{$this->maths->id}", '12.00');
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

test('une matière cochée absente est ignorée dans la moyenne et enregistrée sans note', function () {
    $component = Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->francais->id}", '8')
        ->set("absences.{$this->maths->id}", true);

    expect($component->viewData('preview')['average'])->toBe(16.0);

    $component->call('save')->assertHasNoErrors();

    $mathsGrade = PrimaryGrade::where('primary_subject_id', $this->maths->id)->sole();

    expect($mathsGrade->is_absent)->toBeTrue()
        ->and($mathsGrade->score)->toBeNull();
});

test('cocher « absent à la composition » marque toutes les matières absentes et le résultat affiche « Absence »', function () {
    $component = Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->maths->id}", '16')
        ->set('absentGenerale', true);

    expect($component->get('absences'))->toEqualCanonicalizing([$this->maths->id => true, $this->francais->id => true])
        ->and($component->viewData('preview')['result'])->toBe('Absence');

    $component->call('save')->assertHasNoErrors();

    expect(PrimaryGrade::where('primary_subject_id', $this->maths->id)->sole()->is_absent)->toBeTrue()
        ->and(PrimaryGrade::where('primary_subject_id', $this->francais->id)->sole()->is_absent)->toBeTrue();
});

test('l’appréciation en aperçu suit le barème et disparaît si l’élève est absent', function () {
    $component = Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->maths->id}", '16')
        ->set("scores.{$this->francais->id}", '8');

    expect($component->viewData('preview')['appreciation'])->toBe('Très bien');

    $component->set('absentGenerale', true);

    expect($component->viewData('preview')['appreciation'])->toBeNull();
});

test('une note supérieure au barème de la matière est refusée à la saisie et au blocage de l’enregistrement', function () {
    // Le barème de Français est 10 pour ce niveau (voir beforeEach).
    Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->francais->id}", '12')
        ->assertHasErrors(["scores.{$this->francais->id}" => 'max']);

    Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->francais->id}", '12')
        ->call('save')
        ->assertHasErrors(["scores.{$this->francais->id}" => 'max']);

    expect(PrimaryGrade::where('primary_subject_id', $this->francais->id)->exists())->toBeFalse();
});

test('pour un niveau CP1/CP2/CE1, la moyenne, le seuil de réussite et l’appréciation sont sur 10', function () {
    $classroom = Classroom::factory()->primaireLevel('CP1')->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    $student = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'status' => 'active',
    ]);

    $column = PrimarySubject::coefficientColumn($classroom->level);
    $baremeColumn = PrimarySubject::baremeColumn($classroom->level);
    $maths = PrimarySubject::factory()->create(['name' => 'Mathématiques', $column => 1, $baremeColumn => 20]);

    // AppreciationScale 80 % → « Très bien » déjà créée dans le beforeEach.
    // 16/20 normalisé, ramené sur 10 → moyenne 8/10, 80 % → « Très bien ».
    $component = Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $student])
        ->set("scores.{$maths->id}", '16');

    $preview = $component->viewData('preview');

    expect($preview['average'])->toBe(8.0)
        ->and($preview['result'])->toBe('Admis(e)')
        ->and($preview['appreciation'])->toBe('Très bien')
        ->and($component->viewData('scale'))->toBe(10.0);

    $component->call('save')->assertHasNoErrors();

    expect(ReportCard::sole()->appreciation)->toBe('Très bien');
});

test('le résultat affiche « Admis(e) » si la moyenne atteint 10/20, « Refusé(e) » sinon', function () {
    $component = Livewire::test(EnterStudent::class, ['gradeSheet' => $this->gradeSheet, 'student' => $this->student])
        ->set("scores.{$this->maths->id}", '10')
        ->set("scores.{$this->francais->id}", '5');

    expect($component->viewData('preview')['result'])->toBe('Admis(e)');

    $component->set("scores.{$this->maths->id}", '9')
        ->set("scores.{$this->francais->id}", '4');

    expect($component->viewData('preview')['result'])->toBe('Refusé(e)');
});
