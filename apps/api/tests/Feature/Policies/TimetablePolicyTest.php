<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;

dataset('timetable_manage_allowed_roles', ['fondateur', 'directeur', 'gestionnaire', 'educateur']);
dataset('timetable_manage_denied_roles', ['caissier', 'enseignant']);

test('un rôle habilité peut créer une séance', function (string $role) {
    $establishment = Establishment::factory()->create();
    $user = $role === 'fondateur'
        ? createLocalAdmin($establishment, 'fondateur')
        : createLocalAdmin($establishment, $role);

    actingInEstablishment($establishment);

    expect($user->can('create', TimetableSession::class))->toBeTrue();
})->with('timetable_manage_allowed_roles');

test('un rôle non habilité ne peut pas créer de séance', function (string $role) {
    $establishment = Establishment::factory()->create();
    $user = createLocalAdmin($establishment, $role);

    actingInEstablishment($establishment);

    expect($user->can('create', TimetableSession::class))->toBeFalse();
})->with('timetable_manage_denied_roles');

test('tout le personnel administratif et l’enseignant peuvent consulter la liste', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $roles = ['directeur', 'gestionnaire', 'caissier', 'educateur', 'enseignant'];

    foreach ($roles as $role) {
        $user = createUserWithRole($establishment, $role);
        expect($user->can('viewAny', TimetableSession::class))->toBeTrue();
    }
});

test('un enseignant peut consulter sa propre séance mais pas celle d’un collègue', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishment->id, 'school_year_id' => $schoolYear->id]);
    $subject = Subject::factory()->create();

    $me = createUserWithRole($establishment, 'enseignant');
    $colleague = createUserWithRole($establishment, 'enseignant');

    $mySession = TimetableSession::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'user_id' => $me->id,
        'day_of_week' => 'monday',
        'timetable_slot_id' => TimetableSlot::create([
            'establishment_id' => $establishment->id,
            'label' => '1',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'sequence' => 1,
        ])->id,
    ]);

    $colleagueSession = TimetableSession::create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'user_id' => $colleague->id,
        'day_of_week' => 'tuesday',
        'timetable_slot_id' => $mySession->timetable_slot_id,
    ]);

    expect($me->can('view', $mySession))->toBeTrue()
        ->and($me->can('view', $colleagueSession))->toBeFalse();
});

test('un admin d’un autre établissement ne peut ni voir ni gérer une séance', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();

    actingInEstablishment($establishmentA);
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create(['establishment_id' => $establishmentA->id, 'school_year_id' => $schoolYear->id]);
    $teacher = createUserWithRole($establishmentA, 'enseignant');
    $subject = Subject::factory()->create();
    $slot = TimetableSlot::create([
        'establishment_id' => $establishmentA->id,
        'label' => '1',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'sequence' => 1,
    ]);
    $session = TimetableSession::create([
        'establishment_id' => $establishmentA->id,
        'school_year_id' => $schoolYear->id,
        'classroom_id' => $classroom->id,
        'subject_id' => $subject->id,
        'user_id' => $teacher->id,
        'day_of_week' => 'monday',
        'timetable_slot_id' => $slot->id,
    ]);

    $adminB = createLocalAdmin($establishmentB, 'directeur');
    actingInEstablishment($establishmentB);

    expect($adminB->can('view', $session))->toBeFalse()
        ->and($adminB->can('delete', $session))->toBeFalse();
});
