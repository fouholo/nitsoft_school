<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Direction;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Directions\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer une direction', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('code', 'DR-ABJ')
        ->set('direction_name', 'Direction Régionale Abidjan')
        ->set('address', 'Abidjan')
        ->set('phone_number', '2722000000')
        ->set('email', 'dr-abidjan@education.ci')
        ->set('location', 'Abidjan')
        ->call('save')
        ->assertHasNoErrors();

    $direction = Direction::where('code', 'DR-ABJ')->sole();

    expect($direction->direction_name)->toBe('Direction Régionale Abidjan')
        ->and($direction->address)->toBe('Abidjan')
        ->and($direction->phone_number)->toBe('2722000000')
        ->and($direction->email)->toBe('dr-abidjan@education.ci')
        ->and($direction->location)->toBe('Abidjan')
        ->and($direction->uid_local)->toHaveLength(20)
        ->and($direction->uid_serveur)->toMatch('/^218\d{9}$/');
});

test('un super admin peut modifier une direction, y compris son code', function () {
    $direction = Direction::create(['code' => 'DR-BK1', 'direction_name' => 'Libellé Original']);

    Livewire::test(Index::class)
        ->call('edit', $direction->id)
        ->set('code', 'DR-BK2')
        ->set('direction_name', 'Libellé Modifié')
        ->call('save')
        ->assertHasNoErrors();

    $direction->refresh();

    expect($direction->code)->toBe('DR-BK2')
        ->and($direction->direction_name)->toBe('Libellé Modifié');
});

test('un super admin peut supprimer une direction', function () {
    $direction = Direction::create(['code' => 'DR-DEL', 'direction_name' => 'À supprimer']);

    Livewire::test(Index::class)->call('delete', $direction->id);

    expect(Direction::where('code', 'DR-DEL')->exists())->toBeFalse();
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});
