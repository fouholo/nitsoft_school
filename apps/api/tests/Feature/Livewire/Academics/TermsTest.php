<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Academics\Terms\Index;
use Livewire\Livewire;

test('le placeholder du libellé propose "Composition 1" pour un établissement préscolaire/primaire', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($admin);

    Livewire::test(Index::class)
        ->call('create')
        ->assertSee('Composition 1')
        ->assertDontSee('Trimestre 1');
});

test('le placeholder du libellé propose "Trimestre 1" pour un établissement secondaire', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($admin);

    Livewire::test(Index::class)
        ->call('create')
        ->assertSee('Trimestre 1')
        ->assertDontSee('Composition 1');
});
