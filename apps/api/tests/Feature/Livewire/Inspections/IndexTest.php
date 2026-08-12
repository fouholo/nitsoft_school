<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Direction;
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
        ->set('codeiep', 'IEP001')
        ->set('inspection_name', 'Inspection Test')
        ->set('address', 'Abidjan')
        ->set('phone_number', '2722000000')
        ->set('email', 'iep-test@education.ci')
        ->set('location', 'Abidjan')
        ->call('save')
        ->assertHasNoErrors();

    $inspection = Inspection::where('codeiep', 'IEP001')->sole();

    expect($inspection->inspection_name)->toBe('Inspection Test')
        ->and($inspection->address)->toBe('Abidjan')
        ->and($inspection->phone_number)->toBe('2722000000')
        ->and($inspection->email)->toBe('iep-test@education.ci')
        ->and($inspection->location)->toBe('Abidjan')
        ->and($inspection->uid_local)->toHaveLength(20)
        ->and($inspection->uid_serveur)->toMatch('/^217\d{9}$/');
});

test('un super admin peut modifier une inspection, y compris son codeiep', function () {
    $inspection = Inspection::create(['codeiep' => 'IEP002', 'inspection_name' => 'Libellé Original']);

    Livewire::test(Index::class)
        ->call('edit', $inspection->id)
        ->set('codeiep', 'IEP003')
        ->set('inspection_name', 'Libellé Modifié')
        ->call('save')
        ->assertHasNoErrors();

    $inspection->refresh();

    expect($inspection->codeiep)->toBe('IEP003')
        ->and($inspection->inspection_name)->toBe('Libellé Modifié');
});

test('un super admin peut rattacher une inspection à une direction', function () {
    $direction = Direction::create(['code' => 'DR-ABJ', 'direction_name' => 'Direction Régionale Abidjan']);

    Livewire::test(Index::class)
        ->call('create')
        ->set('codeiep', 'IEP004')
        ->set('inspection_name', 'Inspection Rattachée')
        ->set('uid_direction', $direction->uid_serveur)
        ->call('save')
        ->assertHasNoErrors();

    $inspection = Inspection::where('codeiep', 'IEP004')->sole();

    expect($inspection->direction->code)->toBe('DR-ABJ');
});

test('un super admin peut supprimer une inspection', function () {
    $inspection = Inspection::create(['codeiep' => 'IEP005', 'inspection_name' => 'À supprimer']);

    Livewire::test(Index::class)->call('delete', $inspection->id);

    expect(Inspection::where('codeiep', 'IEP005')->exists())->toBeFalse();
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});
