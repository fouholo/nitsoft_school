<?php

declare(strict_types=1);

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\GuardianPortal\LinkChild;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->parentUser = User::factory()->create();
    $this->guardian = Guardian::factory()->create(['user_id' => $this->parentUser->id]);
    $this->actingAs($this->parentUser);
});

test('une demande de liaison est créée pour un uid valide', function () {
    $establishment = Establishment::factory()->create();
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Livewire::test(LinkChild::class)
        ->set('uid', $student->uid)
        ->call('search')
        ->assertHasNoErrors()
        ->set('relationship', 'mere')
        ->call('requestLink')
        ->assertHasNoErrors();

    $link = $this->guardian->students()->where('students.id', $student->id)->first();

    expect($link)->not->toBeNull()
        ->and($link->pivot->status)->toBe(GuardianLinkStatus::Pending)
        ->and($link->pivot->establishment_id)->toBe($establishment->id);
});

test('un uid inconnu est rejeté sans créer de lien', function () {
    Livewire::test(LinkChild::class)
        ->set('uid', '999999999999')
        ->call('search')
        ->assertHasErrors('uid');

    expect($this->guardian->students()->count())->toBe(0);
});

test('redemander après un rejet réutilise le même lien plutôt que d’en créer un nouveau', function () {
    $establishment = Establishment::factory()->create();
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    $this->guardian->students()->attach($student->id, [
        'establishment_id' => $establishment->id,
        'status' => GuardianLinkStatus::Rejected,
        'relationship' => 'mere',
    ]);

    Livewire::test(LinkChild::class)
        ->set('uid', $student->uid)
        ->call('search')
        ->set('relationship', 'mere')
        ->call('requestLink')
        ->assertHasNoErrors();

    expect($this->guardian->students()->count())->toBe(1);

    $link = $this->guardian->students()->first();
    expect($link->pivot->status)->toBe(GuardianLinkStatus::Pending);
});
