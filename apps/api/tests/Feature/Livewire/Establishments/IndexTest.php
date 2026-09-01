<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Direction;
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
    $inspection = Inspection::create(['codeiep' => 'IEP-001', 'inspection_name' => 'Inspection 1']);

    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'École Indépendante')
        ->set('type', EstablishmentType::PrescolairePrimaire->value)
        ->set('inspection_id', (string) $inspection->id)
        ->call('save')
        ->assertHasNoErrors();

    $establishment = Establishment::where('name', 'École Indépendante')->sole();

    expect($establishment->foundation_id)->toBeNull()
        ->and($establishment->type)->toBe(EstablishmentType::PrescolairePrimaire)
        ->and($establishment->slug)->toBe('ecole-independante');
});

test('un super admin peut créer un établissement rattaché à une fondation', function () {
    $foundation = Foundation::factory()->create();
    $direction = Direction::create(['code' => 'DIR-001', 'direction_name' => 'Direction 1']);

    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'École Rattachée')
        ->set('foundation_id', $foundation->id)
        ->set('type', EstablishmentType::Secondaire->value)
        ->set('direction_id', (string) $direction->id)
        ->call('save')
        ->assertHasNoErrors();

    $establishment = Establishment::where('name', 'École Rattachée')->sole();

    expect($establishment->foundation_id)->toBe($foundation->id);
});

test('le champ inspection ou direction s’affiche selon le type sélectionné', function () {
    // Vérifie le rendu réel de la vue (assertSee), pas seulement la
    // validation — un bug de shadowing de variable Blade (@foreach ($types
    // as $type) qui écrasait la propriété $type du composant) était
    // invisible aux tests qui ne vérifient que les erreurs de validation.
    Livewire::test(Index::class)
        ->call('create')
        ->assertDontSee('wire:model="inspection_id"', false)
        ->assertDontSee('wire:model="direction_id"', false)
        ->set('type', EstablishmentType::PrescolairePrimaire->value)
        ->assertSee('wire:model="inspection_id"', false)
        ->assertDontSee('wire:model="direction_id"', false)
        ->set('type', EstablishmentType::Secondaire->value)
        ->assertDontSee('wire:model="inspection_id"', false)
        ->assertSee('wire:model="direction_id"', false);
});

test('un établissement primaire nécessite une inspection', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'École Sans Inspection')
        ->set('type', EstablishmentType::PrescolairePrimaire->value)
        ->call('save')
        ->assertHasErrors(['inspection_id']);

    expect(Establishment::where('name', 'École Sans Inspection')->exists())->toBeFalse();
});

test('un établissement secondaire nécessite une direction', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'École Sans Direction')
        ->set('type', EstablishmentType::Secondaire->value)
        ->call('save')
        ->assertHasErrors(['direction_id']);

    expect(Establishment::where('name', 'École Sans Direction')->exists())->toBeFalse();
});

test('changer le type d’un établissement efface le lien devenu non pertinent', function () {
    $inspection = Inspection::create(['codeiep' => 'IEP-002', 'inspection_name' => 'Inspection 2']);
    $direction = Direction::create(['code' => 'DIR-002', 'direction_name' => 'Direction 2']);
    $establishment = Establishment::factory()->create([
        'type' => EstablishmentType::PrescolairePrimaire,
        'inspection_id' => $inspection->id,
        'direction_id' => null,
    ]);

    Livewire::test(Index::class)
        ->call('edit', $establishment->id)
        ->set('type', EstablishmentType::Secondaire->value)
        ->set('direction_id', (string) $direction->id)
        ->call('save')
        ->assertHasNoErrors();

    $establishment->refresh();

    expect($establishment->direction_id)->toBe($direction->id)
        ->and($establishment->inspection_id)->toBeNull();
});

test('un super admin peut modifier et supprimer un établissement', function () {
    $inspection = Inspection::create(['codeiep' => 'IEP-004', 'inspection_name' => 'Inspection 4']);
    $establishment = Establishment::factory()->create([
        'foundation_id' => null,
        'type' => EstablishmentType::PrescolairePrimaire,
        'inspection_id' => $inspection->id,
    ]);

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
    $direction = Direction::create(['code' => 'DIR-003', 'direction_name' => 'Direction 3']);

    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'École Logo')
        ->set('type', EstablishmentType::Secondaire->value)
        ->set('direction_id', (string) $direction->id)
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
    $inspection = Inspection::create(['codeiep' => 'IEP-005', 'inspection_name' => 'Inspection 5']);

    $establishment = Establishment::factory()->create([
        'foundation_id' => null,
        'type' => EstablishmentType::PrescolairePrimaire,
        'inspection_id' => $inspection->id,
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
    $inspection = Inspection::create(['codeiep' => 'IEP-TEST', 'inspection_name' => 'Inspection Test']);

    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'École Administrative')
        ->set('type', EstablishmentType::PrescolairePrimaire->value)
        ->set('inspection_id', (string) $inspection->id)
        ->set('opening_code', 'OUV-042')
        ->set('dsps_code', 'DSPS-042')
        ->set('latitude', '5.336400')
        ->set('longitude', '-4.026400')
        ->set('email', 'contact@ecole-administrative.ci')
        ->set('is_arabe', true)
        ->call('save')
        ->assertHasNoErrors();

    $establishment = Establishment::where('name', 'École Administrative')->sole();

    expect($establishment->inspection_id)->toBe($inspection->id)
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
