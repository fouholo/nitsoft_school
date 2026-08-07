<?php

declare(strict_types=1);

use App\Domain\Enrollment\Models\Nationalite;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Students\Show;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->admin = createUserWithRole($this->establishment, 'admin');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);
});

test('le libellé de la nationalité est affiché sur la fiche élève, pas le code', function () {
    Nationalite::create(['code' => 'CIV', 'libelle' => 'Ivoirienne']);

    $student = Student::factory()->create([
        'establishment_id' => $this->establishment->id,
        'nationalite_code' => 'CIV',
        'birth_place' => 'Abidjan',
    ]);

    Livewire::test(Show::class, ['student' => $student])
        ->assertSee('Ivoirienne')
        ->assertSee('Abidjan')
        ->assertDontSee('CIV');
});
