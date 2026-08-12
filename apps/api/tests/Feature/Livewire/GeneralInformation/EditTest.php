<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Livewire\GeneralInformation\Edit;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut modifier les informations générales', function () {
    Livewire::test(Edit::class)
        ->set('nom_ministere', 'Ministère de l’Éducation Nationale')
        ->set('annee_scolaire_courante', '2025-2026')
        ->call('save')
        ->assertHasNoErrors();

    $info = GeneralInformation::current();

    expect($info->nom_ministere)->toBe('Ministère de l’Éducation Nationale')
        ->and($info->annee_scolaire_courante)->toBe('2025-2026');
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Edit::class)->assertForbidden();
});
