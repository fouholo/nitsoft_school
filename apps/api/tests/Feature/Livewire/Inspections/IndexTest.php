<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Inspection;
use App\Livewire\Inspections\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer une inspection', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('code', 'IEP-TEST')
        ->set('libelle', 'Inspection Test')
        ->call('save')
        ->assertHasNoErrors();

    $inspection = Inspection::where('code', 'IEP-TEST')->sole();

    expect($inspection->libelle)->toBe('Inspection Test');
});

test('un super admin peut modifier le libellé d’une inspection, le code reste verrouillé', function () {
    $inspection = Inspection::create(['code' => 'IEP-ORIG', 'libelle' => 'Libellé Original']);

    Livewire::test(Index::class)
        ->call('edit', $inspection->code)
        ->set('code', 'IEP-AUTRE')
        ->set('libelle', 'Libellé Modifié')
        ->call('save')
        ->assertHasNoErrors();

    $inspection->refresh();

    expect($inspection->code)->toBe('IEP-ORIG')
        ->and($inspection->libelle)->toBe('Libellé Modifié');
    expect(Inspection::where('code', 'IEP-AUTRE')->exists())->toBeFalse();
});

test('un super admin peut supprimer une inspection', function () {
    $inspection = Inspection::create(['code' => 'IEP-DEL', 'libelle' => 'À supprimer']);

    Livewire::test(Index::class)->call('delete', $inspection->code);

    expect(Inspection::where('code', 'IEP-DEL')->exists())->toBeFalse();
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});
