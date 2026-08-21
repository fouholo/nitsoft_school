<?php

declare(strict_types=1);

use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\Subjects\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer une matière arabe', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'القرآن الكريم')
        ->set('abbreviation', 'COR')
        ->call('save')
        ->assertHasNoErrors();

    $arabicSubject = ArabicSubject::where('name', 'القرآن الكريم')->sole();

    expect($arabicSubject->abbreviation)->toBe('COR');
});

test('le nom est obligatoire', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);

    expect(ArabicSubject::count())->toBe(0);
});

test('un caissier ne peut pas accéder à l’écran', function () {
    $caissier = createUserWithRole($this->establishment, 'caissier');
    $this->actingAs($caissier);

    Livewire::test(Index::class)->assertForbidden();
});
