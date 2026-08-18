<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Term;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Academics\Terms\Index;
use Livewire\Livewire;

test('l\'écran des périodes est inaccessible pour un établissement préscolaire/primaire', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});

test('le placeholder du libellé propose "Trimestre 1" pour un établissement secondaire', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($admin);

    Livewire::test(Index::class)
        ->call('create')
        ->assertSee('Trimestre 1');
});

test('un admin d’un établissement secondaire peut consulter, créer, modifier et supprimer une période', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $term = Term::factory()->create(['establishment_id' => $establishment->id]);

    expect($admin->can('viewAny', Term::class))->toBeTrue()
        ->and($admin->can('create', Term::class))->toBeTrue()
        ->and($admin->can('view', $term))->toBeTrue()
        ->and($admin->can('update', $term))->toBeTrue()
        ->and($admin->can('delete', $term))->toBeTrue();
});

test('un éducateur d’un établissement secondaire peut consulter, créer, modifier et supprimer une période', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $educateur = createUserWithRole($establishment, 'educateur');
    actingInEstablishment($establishment);

    $term = Term::factory()->create(['establishment_id' => $establishment->id]);

    expect($educateur->can('viewAny', Term::class))->toBeTrue()
        ->and($educateur->can('create', Term::class))->toBeTrue()
        ->and($educateur->can('view', $term))->toBeTrue()
        ->and($educateur->can('update', $term))->toBeTrue()
        ->and($educateur->can('delete', $term))->toBeTrue();
});

test('un enseignant d’un établissement secondaire peut seulement consulter les périodes', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);

    $term = Term::factory()->create(['establishment_id' => $establishment->id]);

    expect($teacher->can('viewAny', Term::class))->toBeTrue()
        ->and($teacher->can('view', $term))->toBeTrue()
        ->and($teacher->can('create', Term::class))->toBeFalse()
        ->and($teacher->can('update', $term))->toBeFalse()
        ->and($teacher->can('delete', $term))->toBeFalse();
});

test('un admin d’un autre établissement ne peut ni voir ni modifier une période', function () {
    $establishmentA = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $establishmentB = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $adminB = createUserWithRole($establishmentB, 'directeur');

    actingInEstablishment($establishmentA);
    $termA = Term::factory()->create(['establishment_id' => $establishmentA->id]);

    actingInEstablishment($establishmentB);

    expect($adminB->can('view', $termA))->toBeFalse()
        ->and($adminB->can('update', $termA))->toBeFalse()
        ->and($adminB->can('delete', $termA))->toBeFalse();
});
