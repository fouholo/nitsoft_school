<?php

declare(strict_types=1);

use App\Domain\Arabic\Models\ArabicGrade;
use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Models\Establishment;

test('un ArabicTerm sans dates fait office de simple numéro de composition', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $arabicTerm = ArabicTerm::factory()->create(['starts_on' => null, 'ends_on' => null, 'sequence' => 2]);

    expect($arabicTerm->starts_on)->toBeNull()
        ->and($arabicTerm->ends_on)->toBeNull()
        ->and($arabicTerm->sequence)->toBe(2);
});

test('un ArabicTerm peut porter des dates pour un niveau secondaire-équivalent', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $arabicTerm = ArabicTerm::factory()->create(['starts_on' => '2026-09-01', 'ends_on' => '2026-12-01']);

    expect($arabicTerm->starts_on?->toDateString())->toBe('2026-09-01')
        ->and($arabicTerm->ends_on?->toDateString())->toBe('2026-12-01');
});

test('le roster d\'un groupe arabe regroupe des élèves de classes françaises différentes', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => false]);

    $enrollmentA = Enrollment::factory()->create(['establishment_id' => $establishment->id, 'arabic_level_id' => $arabicLevel->id]);
    $enrollmentB = Enrollment::factory()->create(['establishment_id' => $establishment->id, 'arabic_level_id' => $arabicLevel->id]);

    // Classes françaises volontairement différentes pour les deux inscriptions.
    expect($enrollmentA->classroom_id)->not->toBe($enrollmentB->classroom_id);

    $roster = Enrollment::where('establishment_id', $establishment->id)
        ->where('arabic_level_id', $arabicLevel->id)
        ->where('arabic_serie_id', null)
        ->pluck('id');

    expect($roster)->toContain($enrollmentA->id, $enrollmentB->id);
});

test('ArabicGrade::updateOrCreate est idempotent par grille × inscription', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $gradeSheet = ArabicGradeSheet::factory()->create(['establishment_id' => $establishment->id]);
    $enrollment = Enrollment::factory()->create(['establishment_id' => $establishment->id]);

    ArabicGrade::updateOrCreate(
        ['arabic_grade_sheet_id' => $gradeSheet->id, 'enrollment_id' => $enrollment->id],
        ['score' => 12]
    );
    ArabicGrade::updateOrCreate(
        ['arabic_grade_sheet_id' => $gradeSheet->id, 'enrollment_id' => $enrollment->id],
        ['score' => 15]
    );

    expect(ArabicGrade::count())->toBe(1)
        ->and((float) ArabicGrade::sole()->score)->toBe(15.0);
});

test('ArabicGrade référence l\'Enrollment, pas le Student', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $enrollment = Enrollment::factory()->create(['establishment_id' => $establishment->id]);
    $grade = ArabicGrade::factory()->create(['establishment_id' => $establishment->id, 'enrollment_id' => $enrollment->id]);

    expect($grade->enrollment->is($enrollment))->toBeTrue();
});

test('un enseignant affecté à un groupe arabe est reconnu via isAssignedToArabicGroup', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $teacher = createUserWithRole($establishment, 'enseignant');
    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => true]);
    $arabicSerie = ArabicSerie::factory()->create();
    $arabicSubject = ArabicSubject::factory()->create();

    ArabicTeacherAssignment::factory()->create([
        'establishment_id' => $establishment->id,
        'user_id' => $teacher->id,
        'arabic_level_id' => $arabicLevel->id,
        'arabic_serie_id' => $arabicSerie->id,
        'arabic_subject_id' => $arabicSubject->id,
    ]);

    expect($teacher->isAssignedToArabicGroup($arabicLevel->id, $arabicSerie->id, $arabicSubject->id))->toBeTrue()
        ->and($teacher->isAssignedToArabicGroup($arabicLevel->id, null, $arabicSubject->id))->toBeFalse();
});

test('les grilles/notes d\'un établissement restent invisibles depuis un autre, le catalogue arabe reste partagé', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();

    $arabicLevel = ArabicLevel::factory()->create();
    $arabicSubject = ArabicSubject::factory()->create();

    actingInEstablishment($establishmentA);
    $gradeSheet = ArabicGradeSheet::factory()->create([
        'establishment_id' => $establishmentA->id,
        'arabic_level_id' => $arabicLevel->id,
        'arabic_subject_id' => $arabicSubject->id,
    ]);

    actingInEstablishment($establishmentB);

    expect(ArabicLevel::whereKey($arabicLevel->id)->exists())->toBeTrue()
        ->and(ArabicSubject::whereKey($arabicSubject->id)->exists())->toBeTrue()
        ->and(ArabicGradeSheet::where('establishment_id', $establishmentB->id)->count())->toBe(0)
        ->and(ArabicGradeSheet::withoutTenant()->whereKey($gradeSheet->id)->exists())->toBeTrue();
});
