<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\AppreciationScale;
use App\Livewire\Academics\AppreciationScales\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer une tranche du barème', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('percentage', '80')
        ->set('appreciation', 'Très bien')
        ->set('tableau_honneur', true)
        ->set('felicitation', true)
        ->call('save')
        ->assertHasNoErrors();

    $scale = AppreciationScale::where('percentage', 80)->sole();

    expect($scale->appreciation)->toBe('Très bien')
        ->and($scale->tableau_honneur)->toBeTrue()
        ->and($scale->tableau_excellence)->toBeFalse()
        ->and($scale->felicitation)->toBeTrue()
        ->and($scale->encouragement)->toBeFalse()
        ->and($scale->uid_serveur)->toMatch('/^235\d{9}$/');
});

test('le pourcentage doit être unique', function () {
    AppreciationScale::factory()->create(['percentage' => 80]);

    Livewire::test(Index::class)
        ->call('create')
        ->set('percentage', '80')
        ->set('appreciation', 'Doublon')
        ->call('save')
        ->assertHasErrors(['percentage']);
});

test('le pourcentage doit être entre 0 et 100', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('percentage', '150')
        ->set('appreciation', 'Hors limite')
        ->call('save')
        ->assertHasErrors(['percentage']);
});

test('modifier une tranche garde son propre pourcentage sans déclencher l’unicité', function () {
    $scale = AppreciationScale::factory()->create(['percentage' => 80, 'appreciation' => 'Très bien']);

    Livewire::test(Index::class)
        ->call('edit', $scale->id)
        ->assertSet('percentage', '80')
        ->set('appreciation', 'Excellent')
        ->call('save')
        ->assertHasNoErrors();

    $scale->refresh();

    expect($scale->appreciation)->toBe('Excellent');
});

test('supprimer une tranche la retire de la liste', function () {
    $scale = AppreciationScale::factory()->create();

    Livewire::test(Index::class)->call('delete', $scale->id);

    expect(AppreciationScale::find($scale->id))->toBeNull();
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});
