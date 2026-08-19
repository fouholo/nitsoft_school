<?php

declare(strict_types=1);

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Students\Show;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->admin = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $this->student = Student::factory()->create(['establishment_id' => $this->establishment->id]);
});

test('désigner un nouveau contact principal décoche les autres pour le même élève', function () {
    $firstGuardian = Guardian::factory()->create();
    $this->student->guardians()->attach($firstGuardian->id, [
        'establishment_id' => $this->establishment->id,
        'status' => GuardianLinkStatus::Approved,
        'relationship' => 'mere',
        'is_primary_contact' => true,
    ]);

    $secondGuardian = Guardian::factory()->create();

    Livewire::test(Show::class, ['student' => $this->student])
        ->set('guardian_id', $secondGuardian->id)
        ->set('relationship', 'pere')
        ->set('is_primary_contact', true)
        ->call('saveGuardian')
        ->assertHasNoErrors();

    $firstLink = $this->student->guardians()->where('guardians.id', $firstGuardian->id)->first();
    $secondLink = $this->student->guardians()->where('guardians.id', $secondGuardian->id)->first();

    expect($firstLink->pivot->is_primary_contact)->toBeFalse()
        ->and($secondLink->pivot->is_primary_contact)->toBeTrue()
        ->and($secondLink->pivot->status)->toBe(GuardianLinkStatus::Approved);
});

test('les erreurs de validation du formulaire de tuteur utilisent des noms de champs en français', function () {
    Livewire::test(Show::class, ['student' => $this->student])
        ->call('addGuardian')
        ->call('saveGuardian')
        ->assertSee('Le champ tuteur est obligatoire.')
        ->assertSee('Le champ rôle est obligatoire.')
        ->assertDontSee('guardian id');
});
