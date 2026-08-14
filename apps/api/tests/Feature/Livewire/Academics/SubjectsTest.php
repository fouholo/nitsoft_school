<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Domain;
use App\Domain\Academics\Models\Subject;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Academics\Subjects\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer une matière rattachée à un domaine', function () {
    $domain = Domain::factory()->create(['name' => 'Sciences']);

    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'Physique-Chimie')
        ->set('is_prescolaire_primaire', false)
        ->set('is_secondaire', true)
        ->set('domain_id', $domain->id)
        ->call('save')
        ->assertHasNoErrors();

    $subject = Subject::where('name', 'Physique-Chimie')->sole();

    expect($subject->is_prescolaire_primaire)->toBeFalse()
        ->and($subject->is_secondaire)->toBeTrue()
        ->and($subject->domain_id)->toBe($domain->id)
        ->and($subject->uid_serveur)->toMatch('/^215\d{9}$/');
});

test('une matière doit être rattachée à au moins un cycle', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'Matière orpheline')
        ->set('is_prescolaire_primaire', false)
        ->set('is_secondaire', false)
        ->call('save')
        ->assertHasErrors(['is_prescolaire_primaire']);

    expect(Subject::where('name', 'Matière orpheline')->exists())->toBeFalse();
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});
