<?php

declare(strict_types=1);

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\GuardianPortal\StudentBilling;
use App\Models\User;
use Livewire\Livewire;

test('un tuteur consulte le détail dû/versé de son enfant', function () {
    $establishment = Establishment::factory()->create();
    $parentUser = User::factory()->create();
    $establishment->users()->attach($parentUser->id, ['role' => 'parent', 'is_active' => true]);
    actingInEstablishment($establishment);

    $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $guardian->students()->attach($student->id, [
        'establishment_id' => $establishment->id,
        'status' => GuardianLinkStatus::Approved,
    ]);

    Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'status' => 'active',
        'registration_amount' => 5000,
        'total_paid' => 2000,
    ]);

    test()->actingAs($parentUser);

    Livewire::test(StudentBilling::class, ['student' => $student])
        ->assertStatus(200)
        ->assertSee(money(5000.0))
        ->assertSee(money(2000.0));
});

test('un tuteur ne peut pas consulter la facturation d’un élève qui n’est pas le sien', function () {
    $establishment = Establishment::factory()->create();
    $parentUser = User::factory()->create();
    $establishment->users()->attach($parentUser->id, ['role' => 'parent', 'is_active' => true]);
    actingInEstablishment($establishment);

    $otherChild = Student::factory()->create(['establishment_id' => $establishment->id]);

    test()->actingAs($parentUser);

    Livewire::test(StudentBilling::class, ['student' => $otherChild])
        ->assertForbidden();
});
