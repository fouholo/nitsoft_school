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
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(Index::class)->assertForbidden();
});

test('approuver une demande la fait passer à approved et provisionne un accès établissement', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    $guardian = pendingLink($this->establishment, $this->student);
    $link = $guardian->students()->where('students.id', $this->student->id)->first()->pivot;

    Livewire::test(Index::class)->call('approve', $link->id);

    $link->refresh();
    expect($link->status)->toBe(GuardianLinkStatus::Approved);

    expect($guardian->user->roleFor($this->establishment->id))->toBe('parent');
});

test('approuver deux fois ne duplique pas l’accès établissement', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    $guardian = pendingLink($this->establishment, $this->student);
    $link = $guardian->students()->where('students.id', $this->student->id)->first()->pivot;

    Livewire::test(Index::class)->call('approve', $link->id);
    Livewire::test(Index::class)->call('approve', $link->id);

    expect($guardian->user->establishments()->wherePivot('role', 'parent')->count())->toBe(1);
});

test('approuver un rôle déjà pourvu rejette l’ancien titulaire', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
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
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    $this->student->update(['mother_name' => 'Aya Kouassi', 'mother_phone' => '+2250700000099']);

    pendingLink($this->establishment, $this->student, 'mere');

    Livewire::test(Index::class)
        ->assertSee('Aya Kouassi')
        ->assertSee('+2250700000099');
});

test('rejeter une demande ne provisionne aucun accès', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    $guardian = pendingLink($this->establishment, $this->student);
    $link = $guardian->students()->where('students.id', $this->student->id)->first()->pivot;

    Livewire::test(Index::class)->call('reject', $link->id);

    $link->refresh();
    expect($link->status)->toBe(GuardianLinkStatus::Rejected)
        ->and($guardian->user->establishments()->count())->toBe(0);
});

test('approuver ou rejeter affiche un message de confirmation visible', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    $guardian = pendingLink($this->establishment, $this->student);
    $link = $guardian->students()->where('students.id', $this->student->id)->first()->pivot;

    Livewire::test(Index::class)
        ->call('approve', $link->id)
        ->assertSet('errorMessage', null)
        ->assertSee('Demande approuvée');

    $otherGuardian = pendingLink($this->establishment, $this->student, 'pere');
    $otherLink = $otherGuardian->students()->where('students.id', $this->student->id)->first()->pivot;

    Livewire::test(Index::class)
        ->call('reject', $otherLink->id)
        ->assertSee('Demande rejetée');
});

test('un tuteur sans compte utilisateur ne peut pas être approuvé et affiche une explication', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    $guardian = Guardian::factory()->create(['user_id' => null]);
    $guardian->students()->attach($this->student->id, [
        'establishment_id' => $this->establishment->id,
        'status' => GuardianLinkStatus::Pending,
        'relationship' => 'mere',
    ]);
    $link = $guardian->students()->where('students.id', $this->student->id)->first()->pivot;

    Livewire::test(Index::class)
        ->assertSee('Compte manquant')
        ->call('approve', $link->id)
        ->assertSee("n'a pas de compte utilisateur");

    $link->refresh();
    expect($link->status)->toBe(GuardianLinkStatus::Pending);
});

test('la vérification signale une correspondance, une non-correspondance et une référence absente', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    $matchingStudent = Student::factory()->create([
        'establishment_id' => $this->establishment->id,
        'mother_name' => 'Aya Kouassi',
        'mother_phone' => '0700000099',
    ]);
    $mismatchStudent = Student::factory()->create([
        'establishment_id' => $this->establishment->id,
        'mother_name' => 'Fatou Diarra',
        'mother_phone' => '0711111111',
    ]);
    $missingStudent = Student::factory()->create([
        'establishment_id' => $this->establishment->id,
        'mother_name' => null,
        'mother_phone' => null,
    ]);

    $matchingGuardian = Guardian::factory()->create(['first_name' => 'Aya', 'last_name' => 'Kouassi', 'phone' => '0700000099']);
    $matchingGuardian->students()->attach($matchingStudent->id, [
        'establishment_id' => $this->establishment->id,
        'status' => GuardianLinkStatus::Pending,
        'relationship' => 'mere',
    ]);

    $mismatchGuardian = Guardian::factory()->create(['first_name' => 'Awa', 'last_name' => 'Traore', 'phone' => '0799999999']);
    $mismatchGuardian->students()->attach($mismatchStudent->id, [
        'establishment_id' => $this->establishment->id,
        'status' => GuardianLinkStatus::Pending,
        'relationship' => 'mere',
    ]);

    $missingGuardian = Guardian::factory()->create();
    $missingGuardian->students()->attach($missingStudent->id, [
        'establishment_id' => $this->establishment->id,
        'status' => GuardianLinkStatus::Pending,
        'relationship' => 'mere',
    ]);

    $component = Livewire::test(Index::class);
    $references = $component->viewData('references');

    $matchingLink = $matchingGuardian->students()->where('students.id', $matchingStudent->id)->first()->pivot;
    $mismatchLink = $mismatchGuardian->students()->where('students.id', $mismatchStudent->id)->first()->pivot;
    $missingLink = $missingGuardian->students()->where('students.id', $missingStudent->id)->first()->pivot;

    expect($references[$matchingLink->id]['match'])->toBe('match')
        ->and($references[$mismatchLink->id]['match'])->toBe('mismatch')
        ->and($references[$missingLink->id]['match'])->toBe('missing');

    $component->assertSee('Correspond')->assertSee('Ne correspond pas')->assertSee('Invérifiable');
});

test('réexaminer une demande rejetée la remet en attente', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    $guardian = pendingLink($this->establishment, $this->student);
    $link = $guardian->students()->where('students.id', $this->student->id)->first()->pivot;
    $link->update(['status' => GuardianLinkStatus::Rejected]);

    Livewire::test(Index::class)
        ->assertSee('Rejetées récemment')
        ->call('reconsider', $link->id)
        ->assertSee('remise en attente');

    $link->refresh();
    expect($link->status)->toBe(GuardianLinkStatus::Pending);
});
