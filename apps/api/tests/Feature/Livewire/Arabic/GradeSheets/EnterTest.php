<?php

declare(strict_types=1);

use App\Domain\Arabic\Models\ArabicGrade;
use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\GradeSheets\Enter;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    actingInEstablishment($this->establishment);

    $this->arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);

    $this->educateur = createUserWithRole($this->establishment, 'educateur');

    $this->gradeSheet = ArabicGradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_level_id' => $this->arabicLevel->id,
        'teacher_id' => $this->educateur->id,
        'max_score' => 20,
    ]);

    $this->enrollmentA = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_level_id' => $this->arabicLevel->id,
    ]);
    $this->enrollmentB = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_level_id' => $this->arabicLevel->id,
    ]);

    // Inscription hors groupe arabe : ne doit jamais apparaître dans le roster.
    Enrollment::factory()->create(['establishment_id' => $this->establishment->id]);
});

test('le roster regroupe les inscriptions du niveau/série arabe visé, indépendamment de la classe', function () {
    $this->actingAs($this->educateur);

    $enrollments = Livewire::test(Enter::class, ['gradeSheet' => $this->gradeSheet])
        ->viewData('enrollments');

    expect($enrollments->pluck('id'))
        ->toContain($this->enrollmentA->id, $this->enrollmentB->id)
        ->toHaveCount(2);
});

test('enregistrer les notes crée un ArabicGrade par inscription', function () {
    $this->actingAs($this->educateur);

    Livewire::test(Enter::class, ['gradeSheet' => $this->gradeSheet])
        ->set("scores.{$this->enrollmentA->id}", '15')
        ->set("scores.{$this->enrollmentB->id}", '10')
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicGrade::count())->toBe(2);

    $gradeA = ArabicGrade::where('enrollment_id', $this->enrollmentA->id)->sole();
    expect((float) $gradeA->score)->toBe(15.0);
});

test('une note hors barème est rejetée', function () {
    $this->actingAs($this->educateur);

    Livewire::test(Enter::class, ['gradeSheet' => $this->gradeSheet])
        ->set("scores.{$this->enrollmentA->id}", '25')
        ->call('save')
        ->assertHasErrors(["scores.{$this->enrollmentA->id}"]);

    expect(ArabicGrade::count())->toBe(0);
});

test('un enseignant non affecté au groupe est refusé', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(Enter::class, ['gradeSheet' => $this->gradeSheet])->assertForbidden();
});

test('un enseignant affecté au groupe, auteur de la grille, peut saisir les notes', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $arabicSubject = ArabicSubject::factory()->create();
    $arabicTerm = ArabicTerm::factory()->create(['establishment_id' => $this->establishment->id]);

    ArabicTeacherAssignment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $teacher->id,
        'arabic_level_id' => $this->arabicLevel->id,
        'arabic_subject_id' => $arabicSubject->id,
    ]);

    $ownGradeSheet = ArabicGradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_level_id' => $this->arabicLevel->id,
        'arabic_subject_id' => $arabicSubject->id,
        'arabic_term_id' => $arabicTerm->id,
        'teacher_id' => $teacher->id,
    ]);

    $this->actingAs($teacher);

    Livewire::test(Enter::class, ['gradeSheet' => $ownGradeSheet])
        ->set("scores.{$this->enrollmentA->id}", '12')
        ->call('save')
        ->assertHasNoErrors();

    // Une ligne ArabicGrade par élève du roster (score null pour ceux non
    // saisis), même comportement que le pré-remplissage du français.
    $gradeA = ArabicGrade::where('enrollment_id', $this->enrollmentA->id)->sole();
    expect((float) $gradeA->score)->toBe(12.0);
});
