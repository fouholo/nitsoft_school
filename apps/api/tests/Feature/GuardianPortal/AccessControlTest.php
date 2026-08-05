<?php

declare(strict_types=1);

use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\GuardianPortal\StudentGrades;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();

    $this->parentUser = User::factory()->create();
    $this->establishment->users()->attach($this->parentUser->id, ['role' => 'parent', 'is_active' => true]);

    actingInEstablishment($this->establishment);

    $this->guardian = Guardian::factory()->create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $this->parentUser->id,
    ]);

    $this->ownChild = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->guardian->students()->attach($this->ownChild->id, ['establishment_id' => $this->establishment->id]);

    $this->otherChild = Student::factory()->create(['establishment_id' => $this->establishment->id]);

    $this->actingAs($this->parentUser);
});

test('un tuteur peut consulter les notes de son propre enfant', function () {
    Livewire::test(StudentGrades::class, ['student' => $this->ownChild])
        ->assertStatus(200);
});

test('un tuteur ne peut pas consulter les notes d’un élève qui n’est pas le sien', function () {
    Livewire::test(StudentGrades::class, ['student' => $this->otherChild])
        ->assertForbidden();
});

test('un utilisateur sans profil tuteur associé est rejeté', function () {
    $userWithoutGuardianProfile = User::factory()->create();
    $this->establishment->users()->attach($userWithoutGuardianProfile->id, ['role' => 'parent', 'is_active' => true]);
    $this->actingAs($userWithoutGuardianProfile);

    Livewire::test(StudentGrades::class, ['student' => $this->ownChild])
        ->assertForbidden();
});
