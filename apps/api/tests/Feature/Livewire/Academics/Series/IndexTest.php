<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Serie;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Academics\Series\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer une série', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('serie', 'C')
        ->set('serie_wording', 'Mathématiques-Sciences physiques')
        ->call('save')
        ->assertHasNoErrors();

    $serie = Serie::where('serie', 'C')->sole();

    expect($serie->serie_wording)->toBe('Mathématiques-Sciences physiques');
});

test('deux séries ne peuvent pas porter le même code', function () {
    Serie::factory()->create(['serie' => 'D']);

    Livewire::test(Index::class)
        ->call('create')
        ->set('serie', 'D')
        ->set('serie_wording', 'Doublon')
        ->call('save')
        ->assertHasErrors(['serie']);
});

test('le code et le libellé sont obligatoires', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('serie', '')
        ->set('serie_wording', '')
        ->call('save')
        ->assertHasErrors(['serie', 'serie_wording']);

    expect(Serie::count())->toBe(0);
});

test('un super admin peut modifier et supprimer une série', function () {
    $serie = Serie::factory()->create(['serie' => 'ANC', 'serie_wording' => 'Ancien libellé']);

    Livewire::test(Index::class)
        ->call('edit', $serie->id)
        ->set('serie_wording', 'Nouveau libellé')
        ->call('save')
        ->assertHasNoErrors();

    expect($serie->refresh()->serie_wording)->toBe('Nouveau libellé');

    Livewire::test(Index::class)->call('delete', $serie->id);

    expect(Serie::where('id', $serie->id)->exists())->toBeFalse();
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $directeur = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($directeur);

    Livewire::test(Index::class)->assertForbidden();
});

test('un fondateur ne peut pas accéder à l’écran', function () {
    $founder = createUserWithRole($this->establishment, 'fondateur');
    $this->actingAs($founder);

    Livewire::test(Index::class)->assertForbidden();
});
