<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Models\ReportCard;

test('un éducateur peut créer, modifier et supprimer une évaluation sans être affecté', function () {
    // Depuis le chantier "privilèges par rôle", seul educateur (parmi les
    // rôles admin-tier) saisit des notes — RolePermissions::MATRIX['grades.enter'].
    $establishment = Establishment::factory()->create();
    $educator = createUserWithRole($establishment, 'educateur');

    actingInEstablishment($establishment);
    $gradeSheet = GradeSheet::factory()->create(['establishment_id' => $establishment->id]);

    expect($educator->can('create', GradeSheet::class))->toBeTrue()
        ->and($educator->can('update', $gradeSheet))->toBeTrue()
        ->and($educator->can('delete', $gradeSheet))->toBeTrue();
});

test('un directeur simple ne peut plus saisir de notes (réservé à educateur)', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);
    $gradeSheet = GradeSheet::factory()->create(['establishment_id' => $establishment->id]);

    expect($admin->can('create', GradeSheet::class))->toBeFalse()
        ->and($admin->can('update', $gradeSheet))->toBeFalse();
});

test('un enseignant sans aucune affectation ne peut pas créer d’évaluation', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    actingInEstablishment($establishment);

    expect($teacher->can('create', GradeSheet::class))->toBeFalse();
});

test('un enseignant affecté peut créer une évaluation et gérer la sienne', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);
    $subject = Subject::factory()->create();

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
    $teacherA = createUserWithRole($establishment, 'enseignant');
    $teacherB = createUserWithRole($establishment, 'enseignant');
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id]);
    $subject = Subject::factory()->create();

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

test('un enseignant avec une affectation classe entière (primaire) peut gérer une évaluation pour n’importe quelle matière de cette classe', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    $classroom = Classroom::factory()->primaire()->create(['establishment_id' => $establishment->id]);
    $subject = Subject::factory()->create();

    TeacherAssignment::factory()->create([
        'establishment_id' => $establishment->id,
        'user_id' => $teacher->id,
        'classroom_id' => $classroom->id,
        'subject_id' => null,
    ]);

    actingInEstablishment($establishment);

    $gradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'type' => 'composition',
    ]);

    expect($teacher->can('update', $gradeSheet))->toBeTrue()
        ->and($teacher->can('delete', $gradeSheet))->toBeTrue();
});

test('un enseignant affecté à une classe peut créer un appel de présence sans matière', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
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

test('seul le titulaire LOCAL_ADMIN/GENERAL_ADMIN gère les affectations enseignants', function () {
    // Depuis le chantier "privilèges par rôle", créer une affectation exige
    // d'être le titulaire du pouvoir (isLocalAdminOf), pas seulement d'avoir
    // un rôle admin — voir TeacherAssignmentPolicy::create().
    $establishment = Establishment::factory()->create();
    $localAdmin = createLocalAdmin($establishment, 'directeur');
    $plainAdmin = createUserWithRole($establishment, 'directeur');
    $teacher = createUserWithRole($establishment, 'enseignant');

    actingInEstablishment($establishment);

    expect($localAdmin->can('viewAny', TeacherAssignment::class))->toBeTrue()
        ->and($localAdmin->can('create', TeacherAssignment::class))->toBeTrue()
        ->and($plainAdmin->can('viewAny', TeacherAssignment::class))->toBeTrue()
        ->and($plainAdmin->can('create', TeacherAssignment::class))->toBeFalse()
        ->and($teacher->can('viewAny', TeacherAssignment::class))->toBeFalse()
        ->and($teacher->can('create', TeacherAssignment::class))->toBeFalse();
});

test('un titulaire gestionnaire ne peut pas affecter d’enseignant (staff.assign exclut gestionnaire)', function () {
    $establishment = Establishment::factory()->create();
    $localAdmin = createLocalAdmin($establishment, 'gestionnaire');

    actingInEstablishment($establishment);

    expect($localAdmin->can('create', TeacherAssignment::class))->toBeFalse();
});

test('un caissier n’a aucun accès aux évaluations', function () {
    $establishment = Establishment::factory()->create();
    $cashier = createUserWithRole($establishment, 'caissier');

    actingInEstablishment($establishment);
    $gradeSheet = GradeSheet::factory()->create(['establishment_id' => $establishment->id]);

    expect($cashier->can('viewAny', GradeSheet::class))->toBeFalse()
        ->and($cashier->can('view', $gradeSheet))->toBeFalse();
});

test('seul un admin peut générer les bulletins, tout le monde peut les consulter', function () {
    $establishment = Establishment::factory()->create();
    $admin = createUserWithRole($establishment, 'directeur');
    $teacher = createUserWithRole($establishment, 'enseignant');

    actingInEstablishment($establishment);

    expect($admin->can('create', ReportCard::class))->toBeTrue()
        ->and($teacher->can('create', ReportCard::class))->toBeFalse()
        ->and($teacher->can('viewAny', ReportCard::class))->toBeTrue();
});
