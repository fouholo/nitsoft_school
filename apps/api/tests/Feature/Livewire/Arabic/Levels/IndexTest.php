<?php

declare(strict_types=1);

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\Levels\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer un niveau arabe', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('code', 'N1')
        ->set('wording', 'المستوى الأول')
        ->set('cycle', Cycle::Primaire->value)
        ->set('requires_series', false)
        ->call('save')
        ->assertHasNoErrors();

    $arabicLevel = ArabicLevel::where('code', 'N1')->sole();

    expect($arabicLevel->wording)->toBe('المستوى الأول')
        ->and($arabicLevel->cycle)->toBe(Cycle::Primaire)
        ->and($arabicLevel->requires_series)->toBeFalse();
});

test('le code est obligatoire', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('code', '')
        ->set('wording', 'مستوى')
        ->set('cycle', Cycle::Secondaire->value)
        ->call('save')
        ->assertHasErrors(['code']);

    expect(ArabicLevel::count())->toBe(0);
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
