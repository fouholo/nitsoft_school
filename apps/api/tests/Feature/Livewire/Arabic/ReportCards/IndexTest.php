<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicGrade;
use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicReportCard;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicSubjectCoefficient;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\ReportCards\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    actingInEstablishment($this->establishment);

    $this->schoolYear = SchoolYear::factory()->create();
    $this->arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);
    $this->arabicClassroom = ArabicClassroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicLevel->id,
    ]);
    $this->arabicTerm = ArabicTerm::factory()->create(['establishment_id' => $this->establishment->id, 'school_year_id' => $this->schoolYear->id]);
    $this->arabicSubject = ArabicSubject::factory()->create();

    ArabicSubjectCoefficient::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_level_id' => $this->arabicLevel->id,
        'arabic_subject_id' => $this->arabicSubject->id,
        'coefficient' => 3,
    ]);

    $this->enrollment = Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
    ]);

    $gradeSheet = ArabicGradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_classroom_id' => $this->arabicClassroom->id,
        'arabic_subject_id' => $this->arabicSubject->id,
        'arabic_term_id' => $this->arabicTerm->id,
    ]);

    ArabicGrade::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_grade_sheet_id' => $gradeSheet->id,
        'enrollment_id' => $this->enrollment->id,
        'score' => 15,
    ]);
});

test('un directeur génère les bulletins arabes d’une classe', function () {
    $directeur = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($directeur);

    Livewire::test(Index::class)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('arabic_term_id', $this->arabicTerm->id)
        ->call('generate')
        ->assertHasNoErrors();

    expect(ArabicReportCard::count())->toBe(1);
});

test('un educateur peut générer les bulletins arabes', function () {
    $educateur = createUserWithRole($this->establishment, 'educateur');
    $this->actingAs($educateur);

    Livewire::test(Index::class)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('arabic_term_id', $this->arabicTerm->id)
        ->call('generate')
        ->assertHasNoErrors();

    expect(ArabicReportCard::count())->toBe(1);
});

test('un fondateur d’un établissement indépendant peut générer les bulletins arabes', function () {
    $founder = createUserWithRole($this->establishment, 'fondateur');
    $this->actingAs($founder);

    Livewire::test(Index::class)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('arabic_term_id', $this->arabicTerm->id)
        ->call('generate')
        ->assertHasNoErrors();

    expect(ArabicReportCard::count())->toBe(1);
});

test('un enseignant ne peut pas générer les bulletins arabes', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(Index::class)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('arabic_term_id', $this->arabicTerm->id)
        ->call('generate')
        ->assertForbidden();

    expect(ArabicReportCard::count())->toBe(0);
});

test('un caissier peut consulter l’écran mais ne peut pas générer (comme le bulletin français)', function () {
    $cashier = createUserWithRole($this->establishment, 'caissier');
    $this->actingAs($cashier);

    Livewire::test(Index::class)
        ->assertOk()
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('arabic_term_id', $this->arabicTerm->id)
        ->call('generate')
        ->assertForbidden();

    expect(ArabicReportCard::count())->toBe(0);
});

test('un établissement non arabe n’a pas accès à l’écran', function () {
    $establishment = Establishment::factory()->create(['is_arabe' => false]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($directeur);

    Livewire::test(Index::class)->assertForbidden();
});

test('une classe arabe d’un autre établissement est refusée', function () {
    $directeur = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($directeur);

    Livewire::test(Index::class)
        ->set('arabic_classroom_id', ArabicClassroom::factory()->create()->id)
        ->set('arabic_term_id', $this->arabicTerm->id)
        ->call('generate')
        ->assertHasErrors(['arabic_classroom_id']);

    expect(ArabicReportCard::count())->toBe(0);
});
