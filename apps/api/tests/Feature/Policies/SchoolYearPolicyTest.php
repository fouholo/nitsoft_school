<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Establishments\Models\Establishment;

test('un directeur peut consulter le calendrier partagé mais ne peut ni créer, ni modifier, ni supprimer', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create();

    expect($directeur->can('viewAny', SchoolYear::class))->toBeTrue()
        ->and($directeur->can('view', $schoolYear))->toBeTrue()
        ->and($directeur->can('create', SchoolYear::class))->toBeFalse()
        ->and($directeur->can('update', $schoolYear))->toBeFalse()
        ->and($directeur->can('delete', $schoolYear))->toBeFalse();
});

test('un enseignant peut seulement consulter', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');

    actingInEstablishment($establishment);

    $schoolYear = SchoolYear::factory()->create();

    expect($teacher->can('viewAny', SchoolYear::class))->toBeTrue()
        ->and($teacher->can('view', $schoolYear))->toBeTrue()
        ->and($teacher->can('create', SchoolYear::class))->toBeFalse()
        ->and($teacher->can('update', $schoolYear))->toBeFalse()
        ->and($teacher->can('delete', $schoolYear))->toBeFalse();
});

test('une même année scolaire est visible par des membres d’établissements différents', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();
    $directeurA = createUserWithRole($establishmentA, 'directeur');
    $directeurB = createUserWithRole($establishmentB, 'directeur');

    $schoolYear = SchoolYear::factory()->create();

    actingInEstablishment($establishmentA);
    expect($directeurA->can('view', $schoolYear))->toBeTrue();

    actingInEstablishment($establishmentB);
    expect($directeurB->can('view', $schoolYear))->toBeTrue();
});

test('un administrateur SaaS peut créer, modifier et supprimer une année scolaire sans être membre d’aucun établissement', function () {
    $superAdmin = createSaasAdmin('main');

    expect($superAdmin->can('create', SchoolYear::class))->toBeTrue();

    $schoolYear = SchoolYear::factory()->create();

    expect($superAdmin->can('update', $schoolYear))->toBeTrue()
        ->and($superAdmin->can('delete', $schoolYear))->toBeTrue();
});

test('le bouton « Nouvelle année » n’apparaît pas pour un directeur', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire\Livewire::test(App\Livewire\Academics\SchoolYears\Index::class)
        ->assertDontSee('Nouvelle année');
});

test('un directeur ne peut pas créer d’année scolaire même en appelant le composant directement', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');

    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    Livewire\Livewire::test(App\Livewire\Academics\SchoolYears\Index::class)
        ->call('create')
        ->assertForbidden();
});
