<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Livewire\GeneralInformation\Edit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('un super admin peut téléverser une armoirie', function () {
    Storage::fake('public');

    Livewire::test(Edit::class)
        ->set('armoirie', UploadedFile::fake()->image('armoirie.png')->size(50))
        ->call('save')
        ->assertHasNoErrors();

    $info = GeneralInformation::current();

    expect($info->armoirie_path)->not->toBeNull();
    Storage::disk('public')->assertExists($info->armoirie_path);
});

test('remplacer l’armoirie supprime l’ancienne du stockage', function () {
    Storage::fake('public');
    Storage::disk('public')->put('general-information/old.png', 'contenu-factice');

    GeneralInformation::current()->update(['armoirie_path' => 'general-information/old.png']);

    Livewire::test(Edit::class)
        ->set('armoirie', UploadedFile::fake()->image('new.png')->size(50))
        ->call('save')
        ->assertHasNoErrors();

    $info = GeneralInformation::current();

    Storage::disk('public')->assertMissing('general-information/old.png');
    Storage::disk('public')->assertExists($info->armoirie_path);
});

test('un super admin peut téléverser une image de fond pour la carte d’identité scolaire', function () {
    Storage::fake('public');

    Livewire::test(Edit::class)
        ->set('cardBackground', UploadedFile::fake()->image('fond.jpg')->size(200))
        ->call('save')
        ->assertHasNoErrors();

    $info = GeneralInformation::current();

    expect($info->card_background_path)->not->toBeNull();
    Storage::disk('public')->assertExists($info->card_background_path);
});

test('remplacer l’image de fond de la carte supprime l’ancienne du stockage', function () {
    Storage::fake('public');
    Storage::disk('public')->put('general-information/old-fond.jpg', 'contenu-factice');

    GeneralInformation::current()->update(['card_background_path' => 'general-information/old-fond.jpg']);

    Livewire::test(Edit::class)
        ->set('cardBackground', UploadedFile::fake()->image('nouveau-fond.jpg')->size(200))
        ->call('save')
        ->assertHasNoErrors();

    $info = GeneralInformation::current();

    Storage::disk('public')->assertMissing('general-information/old-fond.jpg');
    Storage::disk('public')->assertExists($info->card_background_path);
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Edit::class)->assertForbidden();
});
