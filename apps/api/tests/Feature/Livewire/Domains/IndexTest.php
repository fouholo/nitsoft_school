<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Domain;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Domains\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer un domaine', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'Sciences')
        ->call('save')
        ->assertHasNoErrors();

    $domain = Domain::where('name', 'Sciences')->sole();

    expect($domain->uid_local)->toHaveLength(20)
        ->and($domain->uid_serveur)->toMatch('/^219\d{9}$/');
});

test('deux domaines ne peuvent pas porter le même nom', function () {
    Domain::factory()->create(['name' => 'Lettres']);

    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'Lettres')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('un super admin peut modifier et supprimer un domaine', function () {
    $domain = Domain::factory()->create(['name' => 'Ancien nom']);

    Livewire::test(Index::class)
        ->call('edit', $domain->id)
        ->set('name', 'Nouveau nom')
        ->call('save')
        ->assertHasNoErrors();

    expect($domain->refresh()->name)->toBe('Nouveau nom');

    Livewire::test(Index::class)->call('delete', $domain->id);

    expect(Domain::where('id', $domain->id)->exists())->toBeFalse();
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});
