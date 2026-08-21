<?php

declare(strict_types=1);

use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\Series\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer une série arabe', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('serie', 'S1')
        ->set('serie_wording', 'حفظ القرآن')
        ->call('save')
        ->assertHasNoErrors();

    $arabicSerie = ArabicSerie::where('serie', 'S1')->sole();

    expect($arabicSerie->serie_wording)->toBe('حفظ القرآن');
});

test('le libellé est obligatoire', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('serie', 'S1')
        ->set('serie_wording', '')
        ->call('save')
        ->assertHasErrors(['serie_wording']);

    expect(ArabicSerie::count())->toBe(0);
});

test('un gestionnaire d’établissement ne peut pas accéder à l’écran', function () {
    $gestionnaire = createUserWithRole($this->establishment, 'gestionnaire');
    $this->actingAs($gestionnaire);

    Livewire::test(Index::class)->assertForbidden();
});
