<?php

declare(strict_types=1);

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Guardians\Index;
use Livewire\Livewire;

test('la liste des tuteurs se charge sans erreur et ne montre que les liens approuvés de l’établissement courant', function () {
    // Régression : whereHas('students', fn ($q) => $q->wherePivot(...)) plantait
    // en SQL ("Column not found: pivot") — wherePivot() n'existe pas sur le
    // Builder reçu par la closure whereHas(), contrairement à un vrai objet
    // de relation BelongsToMany.
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $approvedGuardian = Guardian::factory()->create(['last_name' => 'Approuvé']);
    $pendingGuardian = Guardian::factory()->create(['last_name' => 'EnAttente']);

    $student->guardians()->attach([
        $approvedGuardian->id => ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Approved],
        $pendingGuardian->id => ['establishment_id' => $establishment->id, 'status' => GuardianLinkStatus::Pending],
    ]);

    $guardians = Livewire::test(Index::class)->viewData('guardians');

    expect($guardians->pluck('id'))->toContain($approvedGuardian->id)
        ->not->toContain($pendingGuardian->id);
});
