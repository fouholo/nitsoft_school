<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Direction;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Directions\Index;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('un super admin peut téléverser un logo pour une direction', function () {
    Storage::fake('public');

    Livewire::test(Index::class)
        ->call('create')
        ->set('code', 'DR-LOG')
        ->set('direction_name', 'Direction Logo')
        ->set('logo', UploadedFile::fake()->image('logo.jpg')->size(50))
        ->call('save')
        ->assertHasNoErrors();

    $direction = Direction::where('code', 'DR-LOG')->sole();

    expect($direction->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($direction->logo_path);
});

test('remplacer le logo d’une direction supprime l’ancien du stockage', function () {
    Storage::fake('public');
    Storage::disk('public')->put('directions-logos/old.jpg', 'contenu-factice');

    $direction = Direction::create([
        'code' => 'DR-OLD',
        'direction_name' => 'Direction Ancien Logo',
        'logo_path' => 'directions-logos/old.jpg',
    ]);

    Livewire::test(Index::class)
        ->call('edit', $direction->id)
        ->set('logo', UploadedFile::fake()->image('new.jpg')->size(50))
        ->call('save')
        ->assertHasNoErrors();

    $direction->refresh();

    Storage::disk('public')->assertMissing('directions-logos/old.jpg');
    Storage::disk('public')->assertExists($direction->logo_path);
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});
