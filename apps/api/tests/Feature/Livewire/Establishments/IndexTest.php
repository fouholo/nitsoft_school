<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Livewire\Establishments\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer un établissement indépendant', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'École Indépendante')
        ->set('type', EstablishmentType::PrescolairePrimaire->value)
        ->call('save')
        ->assertHasNoErrors();

    $establishment = Establishment::where('name', 'École Indépendante')->sole();

    expect($establishment->foundation_id)->toBeNull()
        ->and($establishment->type)->toBe(EstablishmentType::PrescolairePrimaire)
        ->and($establishment->slug)->toBe('ecole-independante');
});

test('un super admin peut créer un établissement rattaché à une fondation', function () {
    $foundation = Foundation::factory()->create();

    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'École Rattachée')
        ->set('foundation_id', $foundation->id)
        ->set('type', EstablishmentType::Secondaire->value)
        ->call('save')
        ->assertHasNoErrors();

    $establishment = Establishment::where('name', 'École Rattachée')->sole();

    expect($establishment->foundation_id)->toBe($foundation->id);
});

test('un super admin peut modifier et supprimer un établissement', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);

    Livewire::test(Index::class)
        ->call('edit', $establishment->id)
        ->set('name', 'Nom Modifié')
        ->call('save')
        ->assertHasNoErrors();

    expect($establishment->fresh()->name)->toBe('Nom Modifié');

    Livewire::test(Index::class)->call('delete', $establishment->id);

    expect(Establishment::find($establishment->id))->toBeNull();
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});
