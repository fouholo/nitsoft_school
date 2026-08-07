<?php

declare(strict_types=1);

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\GuardianLinkRequests\Index;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    actingInEstablishment($this->establishment);
    $this->student = Student::factory()->create(['establishment_id' => $this->establishment->id]);
});

function pendingLink(Establishment $establishment, Student $student, string $relationship = 'mere'): Guardian
{
    $parentUser = User::factory()->create();
    $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);

    $guardian->students()->attach($student->id, [
        'establishment_id' => $establishment->id,
        'status' => GuardianLinkStatus::Pending,
        'relationship' => $relationship,
    ]);

    return $guardian;
}

test('un non-admin ne peut pas accéder à l’écran', function () {
    $teacher = createUserWithRole($this->establishment, 'teacher');
    $this->actingAs($teacher);

    Livewire::test(Index::class)->assertForbidden();
});

test('approuver une demande la fait passer à approved et provisionne un accès établissement', function () {
    $admin = createUserWithRole($this->establishment, 'admin');
    $this->actingAs($admin);

    $guardian = pendingLink($this->establishment, $this->student);
    $link = $guardian->students()->where('students.id', $this->student->id)->first()->pivot;

    Livewire::test(Index::class)->call('approve', $link->id);

    $link->refresh();
    expect($link->status)->toBe(GuardianLinkStatus::Approved);

    expect($guardian->user->roleFor($this->establishment->id))->toBe('parent');
});

test('approuver deux fois ne duplique pas l’accès établissement', function () {
    $admin = createUserWithRole($this->establishment, 'admin');
    $this->actingAs($admin);

    $guardian = pendingLink($this->establishment, $this->student);
    $link = $guardian->students()->where('students.id', $this->student->id)->first()->pivot;

    Livewire::test(Index::class)->call('approve', $link->id);
    Livewire::test(Index::class)->call('approve', $link->id);

    expect($guardian->user->establishments()->wherePivot('role', 'parent')->count())->toBe(1);
});

test('approuver un rôle déjà pourvu rejette l’ancien titulaire', function () {
    $admin = createUserWithRole($this->establishment, 'admin');
    $this->actingAs($admin);

    $existingGuardian = pendingLink($this->establishment, $this->student, 'mere');
    $existingLink = $existingGuardian->students()->where('students.id', $this->student->id)->first()->pivot;
    $existingLink->update(['status' => GuardianLinkStatus::Approved]);

    $newGuardian = pendingLink($this->establishment, $this->student, 'mere');
    $newLink = $newGuardian->students()->where('students.id', $this->student->id)->first()->pivot;

    Livewire::test(Index::class)->call('approve', $newLink->id);

    $existingLink->refresh();
    $newLink->refresh();

    expect($newLink->status)->toBe(GuardianLinkStatus::Approved)
        ->and($existingLink->status)->toBe(GuardianLinkStatus::Rejected);
});

test('la référence école correspondant au rôle demandé est affichée', function () {
    $admin = createUserWithRole($this->establishment, 'admin');
    $this->actingAs($admin);

    $this->student->update(['mother_name' => 'Aya Kouassi', 'mother_phone' => '+2250700000099']);

    pendingLink($this->establishment, $this->student, 'mere');

    Livewire::test(Index::class)
        ->assertSee('Aya Kouassi')
        ->assertSee('+2250700000099');
});

test('rejeter une demande ne provisionne aucun accès', function () {
    $admin = createUserWithRole($this->establishment, 'admin');
    $this->actingAs($admin);

    $guardian = pendingLink($this->establishment, $this->student);
    $link = $guardian->students()->where('students.id', $this->student->id)->first()->pivot;

    Livewire::test(Index::class)->call('reject', $link->id);

    $link->refresh();
    expect($link->status)->toBe(GuardianLinkStatus::Rejected)
        ->and($guardian->user->establishments()->count())->toBe(0);
});
