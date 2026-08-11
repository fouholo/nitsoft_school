<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\Inspection;
use App\Livewire\Establishments\Index;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('un super admin peut téléverser un logo pour un établissement', function () {
    Storage::fake('public');

    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'École Logo')
        ->set('type', EstablishmentType::Secondaire->value)
        ->set('logo', UploadedFile::fake()->image('logo.jpg')->size(50))
        ->call('save')
        ->assertHasNoErrors();

    $establishment = Establishment::where('name', 'École Logo')->sole();

    expect($establishment->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($establishment->logo_path);
});

test('remplacer le logo d’un établissement supprime l’ancien du stockage', function () {
    Storage::fake('public');
    Storage::disk('public')->put('establishments-logos/old.jpg', 'contenu-factice');

    $establishment = Establishment::factory()->create([
        'foundation_id' => null,
        'logo_path' => 'establishments-logos/old.jpg',
    ]);

    Livewire::test(Index::class)
        ->call('edit', $establishment->id)
        ->set('logo', UploadedFile::fake()->image('new.jpg')->size(50))
        ->call('save')
        ->assertHasNoErrors();

    $establishment->refresh();

    Storage::disk('public')->assertMissing('establishments-logos/old.jpg');
    Storage::disk('public')->assertExists($establishment->logo_path);
});

test('un super admin peut renseigner les champs administratifs d’un établissement', function () {
    $inspection = Inspection::create(['code' => 'IEP-TEST', 'libelle' => 'Inspection Test']);

    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'École Administrative')
        ->set('type', EstablishmentType::Secondaire->value)
        ->set('inspection_code', $inspection->code)
        ->set('opening_code', 'OUV-042')
        ->set('dsps_code', 'DSPS-042')
        ->set('latitude', '5.336400')
        ->set('longitude', '-4.026400')
        ->set('email', 'contact@ecole-administrative.ci')
        ->set('is_arabe', true)
        ->call('save')
        ->assertHasNoErrors();

    $establishment = Establishment::where('name', 'École Administrative')->sole();

    expect($establishment->inspection_code)->toBe('IEP-TEST')
        ->and($establishment->opening_code)->toBe('OUV-042')
        ->and($establishment->dsps_code)->toBe('DSPS-042')
        ->and($establishment->email)->toBe('contact@ecole-administrative.ci')
        ->and($establishment->is_arabe)->toBeTrue();
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});
