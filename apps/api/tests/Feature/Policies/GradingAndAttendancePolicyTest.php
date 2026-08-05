<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Models\ReportCard;

test('un admin peut créer, modifier et supprimer une évaluation sans être affecté', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'admin');

    actingInEstablishment($establishment);
    $gradeSheet = GradeSheet::factory()->create(['establishment_id' => $establishment->id]);

    expect($admin->can('create', GradeSheet::class))->toBeTrue()
        ->and($admin->can('update', $gradeSheet))->toBeTrue()
        ->and($admin->can('delete', $gradeSheet))->toBeTrue();
});

test('un enseignant sans aucune affectation ne peut pas créer d’évaluation', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'teacher');

    actingInEstablishment($establishment);

    expect($teacher->can('create', GradeSheet::class))->toBeFalse();
});

test('un enseignant affecté peut créer une évaluation et gérer la sienne', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'teacher');
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);
    $subject = Subject::factory()->create(['establishment_id' => $establishment->id]);

    TeacherAssignment::factory()->create([
        'establishment_id' => $establishment->id,
        'user_id' => $teacher->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
    ]);

    actingInEstablishment($establishment);

    $ownGradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
    ]);

    expect($teacher->can('create', GradeSheet::class))->toBeTrue()
        ->and($teacher->can('update', $ownGradeSheet))->toBeTrue()
        ->and($teacher->can('delete', $ownGradeSheet))->toBeTrue();
});

test('un enseignant ne peut pas modifier l’évaluation d’un collègue', function () {
    $establishment = Establishment::factory()->create();
    $teacherA = createUserWithRole($establishment, 'teacher');
    $teacherB = createUserWithRole($establishment, 'teacher');
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);
    $subject = Subject::factory()->create(['establishment_id' => $establishment->id]);

    foreach ([$teacherA, $teacherB] as $teacher) {
        TeacherAssignment::factory()->create([
            'establishment_id' => $establishment->id,
            'user_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
        ]);
    }

    actingInEstablishment($establishment);

    $gradeSheetOfA = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacherA->id,
    ]);

    expect($teacherB->can('update', $gradeSheetOfA))->toBeFalse()
        ->and($teacherB->can('delete', $gradeSheetOfA))->toBeFalse();
});

test('un enseignant affecté à une classe peut créer un appel de présence sans matière', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'teacher');
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);

    TeacherAssignment::factory()->create([
        'establishment_id' => $establishment->id,
        'user_id' => $teacher->id,
        'classroom_id' => $classroom->id,
    ]);

    actingInEstablishment($establishment);

    $session = AttendanceSession::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'teacher_id' => $teacher->id,
    ]);

    expect($teacher->can('create', AttendanceSession::class))->toBeTrue()
        ->and($teacher->can('update', $session))->toBeTrue();
});

test('seul un admin gère les affectations enseignants', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'admin');
    $teacher = createUserWithRole($establishment, 'teacher');

    actingInEstablishment($establishment);

    expect($admin->can('viewAny', TeacherAssignment::class))->toBeTrue()
        ->and($admin->can('create', TeacherAssignment::class))->toBeTrue()
        ->and($teacher->can('viewAny', TeacherAssignment::class))->toBeFalse()
        ->and($teacher->can('create', TeacherAssignment::class))->toBeFalse();
});

test('seul un admin peut générer les bulletins, tout le monde peut les consulter', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'admin');
    $teacher = createUserWithRole($establishment, 'teacher');

    actingInEstablishment($establishment);

    expect($admin->can('create', ReportCard::class))->toBeTrue()
        ->and($teacher->can('create', ReportCard::class))->toBeFalse()
        ->and($teacher->can('viewAny', ReportCard::class))->toBeTrue();
});
