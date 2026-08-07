<?php

declare(strict_types=1);

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
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
        'user_id' => $this->parentUser->id,
    ]);

    $this->ownChild = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->guardian->students()->attach($this->ownChild->id, [
        'establishment_id' => $this->establishment->id,
        'status' => GuardianLinkStatus::Approved,
    ]);

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

test('un lien en attente de validation ne donne pas accès à l’élève', function () {
    $pendingChild = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->guardian->students()->attach($pendingChild->id, [
        'establishment_id' => $this->establishment->id,
        'status' => GuardianLinkStatus::Pending,
    ]);

    Livewire::test(StudentGrades::class, ['student' => $pendingChild])
        ->assertForbidden();
});
