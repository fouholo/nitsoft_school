<?php

declare(strict_types=1);

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\GuardianPortal\Dashboard;
use App\Models\User;
use Livewire\Livewire;

function actingAsGuardian(Establishment $establishment): Guardian
{
    $parentUser = User::factory()->create();
    $establishment->users()->attach($parentUser->id, ['role' => 'parent', 'is_active' => true]);
    actingInEstablishment($establishment);

    $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);

    test()->actingAs($parentUser);

    return $guardian;
}

test('seuls les enfants au statut approuvé apparaissent, sans bannière si aucune demande en attente', function () {
    $establishment = Establishment::factory()->create();
    $guardian = actingAsGuardian($establishment);

    $approvedChild = Student::factory()->create(['establishment_id' => $establishment->id, 'first_name' => 'Awa', 'last_name' => 'Koné']);
    $guardian->students()->attach($approvedChild->id, ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Approved]);

    $rejectedChild = Student::factory()->create(['establishment_id' => $establishment->id, 'first_name' => 'Ali', 'last_name' => 'Traoré']);
    $guardian->students()->attach($rejectedChild->id, ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Rejected]);

    Livewire::test(Dashboard::class)
        ->assertSee('Koné')
        ->assertDontSee('Traoré')
        ->assertDontSee('en attente');
});

test('une demande en attente affiche une bannière distincte de l’état vraiment vide', function () {
    $establishment = Establishment::factory()->create();
    $guardian = actingAsGuardian($establishment);

    $pendingChild = Student::factory()->create(['establishment_id' => $establishment->id]);
    $guardian->students()->attach($pendingChild->id, ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Pending]);

    Livewire::test(Dashboard::class)
        ->assertSee('1 demande')
        ->assertSee('en attente')
        ->assertDontSee('Aucun enfant rattaché');
});

test('l’état vraiment vide s’affiche seulement en l’absence de toute demande', function () {
    $establishment = Establishment::factory()->create();
    actingAsGuardian($establishment);

    Livewire::test(Dashboard::class)
        ->assertSee('Aucun enfant rattaché')
        ->assertDontSee('en attente');
});

test('la classe affichée provient de l’inscription active, pas d’une inscription plus ancienne', function () {
    $establishment = Establishment::factory()->create();
    $guardian = actingAsGuardian($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $guardian->students()->attach($student->id, ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Approved]);

    $oldClassroom = Classroom::factory()->create([
        'establishment_id' => $establishment->id,
        'level_id' => Level::factory()->state(['level_wording' => '5ème (ancienne)']),
        'numero' => 'B',
    ]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $oldClassroom->id,
        'status' => 'withdrawn',
        'enrolled_on' => now()->subYear()->toDateString(),
    ]);

    $currentClassroom = Classroom::factory()->create([
        'establishment_id' => $establishment->id,
        'level_id' => Level::factory()->state(['level_wording' => '6ème']),
        'numero' => 'A',
    ]);
    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'classroom_id' => $currentClassroom->id,
        'status' => 'active',
        'enrolled_on' => now()->toDateString(),
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee('6ème A')
        ->assertDontSee('5ème (ancienne)');
});

test('un solde en retard affiche un badge sur le lien Facturation', function () {
    $establishment = Establishment::factory()->create();
    $guardian = actingAsGuardian($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $guardian->students()->attach($student->id, ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Approved]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'status' => 'active',
        'registration_amount' => 10000,
        'total_paid' => 0,
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee('Retard');
});

test('aucun badge de retard si le solde est à jour', function () {
    $establishment = Establishment::factory()->create();
    $guardian = actingAsGuardian($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $guardian->students()->attach($student->id, ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Approved]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'status' => 'active',
        'registration_amount' => 10000,
        'total_paid' => 10000,
    ]);

    Livewire::test(Dashboard::class)
        ->assertDontSee('Retard');
});

test('une absence dans les 7 derniers jours affiche un badge sur le lien Présences', function () {
    $establishment = Establishment::factory()->create();
    $guardian = actingAsGuardian($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $guardian->students()->attach($student->id, ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Approved]);

    $session = AttendanceSession::factory()->create([
        'establishment_id' => $establishment->id,
        'session_date' => now()->subDays(2)->toDateString(),
    ]);
    AttendanceRecord::factory()->create([
        'establishment_id' => $establishment->id,
        'attendance_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'absent',
    ]);

    Livewire::test(Dashboard::class)
        ->assertSee('Absence récente');
});

test('une absence ancienne (plus de 7 jours) n’affiche pas de badge', function () {
    $establishment = Establishment::factory()->create();
    $guardian = actingAsGuardian($establishment);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $guardian->students()->attach($student->id, ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Approved]);

    $session = AttendanceSession::factory()->create([
        'establishment_id' => $establishment->id,
        'session_date' => now()->subDays(30)->toDateString(),
    ]);
    AttendanceRecord::factory()->create([
        'establishment_id' => $establishment->id,
        'attendance_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'absent',
    ]);

    Livewire::test(Dashboard::class)
        ->assertDontSee('Absence récente');
});
