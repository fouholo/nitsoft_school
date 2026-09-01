<?php

declare(strict_types=1);

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Level;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Academics\Levels\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer un niveau', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('level', 'CP1')
        ->set('level_wording', 'Cours préparatoire 1ère année')
        ->set('cycle', Cycle::Primaire->value)
        ->set('requires_series', false)
        ->call('save')
        ->assertHasNoErrors();

    $level = Level::where('level', 'CP1')->sole();

    expect($level->level_wording)->toBe('Cours préparatoire 1ère année')
        ->and($level->cycle)->toBe(Cycle::Primaire)
        ->and($level->requires_series)->toBeFalse();
});

test('deux niveaux ne peuvent pas porter le même code', function () {
    Level::factory()->create(['level' => 'TLE']);

    Livewire::test(Index::class)
        ->call('create')
        ->set('level', 'TLE')
        ->set('level_wording', 'Terminale')
        ->set('cycle', Cycle::Secondaire->value)
        ->call('save')
        ->assertHasErrors(['level']);
});

test('le code et le libellé sont obligatoires', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('level', '')
        ->set('level_wording', '')
        ->set('cycle', Cycle::Secondaire->value)
        ->call('save')
        ->assertHasErrors(['level', 'level_wording']);

    expect(Level::count())->toBe(0);
});

test('un super admin peut modifier et supprimer un niveau', function () {
    $level = Level::factory()->create(['level' => 'ANC', 'level_wording' => 'Ancien libellé']);

    Livewire::test(Index::class)
        ->call('edit', $level->id)
        ->set('level_wording', 'Nouveau libellé')
        ->set('requires_series', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($level->refresh())
        ->level_wording->toBe('Nouveau libellé')
        ->requires_series->toBeTrue();

    Livewire::test(Index::class)->call('delete', $level->id);

    expect(Level::where('id', $level->id)->exists())->toBeFalse();
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
