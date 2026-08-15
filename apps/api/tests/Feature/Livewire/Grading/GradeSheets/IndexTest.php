<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Grading\GradeSheets\Index;
use Livewire\Livewire;

test('le dispatcher route vers l’écran primaire pour un établissement préscolaire/primaire', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($admin);

    Livewire::test(Index::class)
        ->assertViewHas('isPrimaire', true)
        ->assertSeeLivewire('grading.grade-sheets.primaire.index');
});

test('le dispatcher route vers l’écran secondaire pour un établissement secondaire', function () {
    $establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire]);
    $admin = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($admin);

    Livewire::test(Index::class)
        ->assertViewHas('isPrimaire', false)
        ->assertSeeLivewire('grading.grade-sheets.secondaire.index');
});
