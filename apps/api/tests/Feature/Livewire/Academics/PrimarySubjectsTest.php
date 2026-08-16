<?php

declare(strict_types=1);

use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Academics\PrimarySubjects\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->superAdmin = createSaasAdmin('main');

    actingInEstablishment($this->establishment);
    $this->actingAs($this->superAdmin);
});

test('un super admin peut créer une matière primaire avec des coefficients et des barèmes par niveau', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'Mathématiques')
        ->set('abbreviation', 'MATHS')
        ->set('coefficient_cp1', '2')
        ->set('coefficient_cp2', '2')
        ->set('coefficient_cm2', '3')
        ->set('bareme_cp1', '20')
        ->set('bareme_cp2', '20')
        ->set('bareme_cm2', '10')
        ->call('save')
        ->assertHasNoErrors();

    $subject = PrimarySubject::where('name', 'Mathématiques')->sole();

    expect($subject->abbreviation)->toBe('MATHS')
        ->and((float) $subject->coefficient_cp1)->toBe(2.0)
        ->and((float) $subject->coefficient_cp2)->toBe(2.0)
        ->and($subject->coefficient_ce1)->toBeNull()
        ->and((float) $subject->coefficient_cm2)->toBe(3.0)
        ->and((float) $subject->bareme_cp1)->toBe(20.0)
        ->and($subject->bareme_ce1)->toBeNull()
        ->and((float) $subject->bareme_cm2)->toBe(10.0)
        ->and($subject->uid_serveur)->toMatch('/^224\d{9}$/');
});

test('un coefficient laissé vide n’est pas configuré pour ce niveau', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'Anglais')
        ->set('abbreviation', 'ANGL')
        ->set('coefficient_cm1', '1')
        ->call('save')
        ->assertHasNoErrors();

    $subject = PrimarySubject::where('name', 'Anglais')->sole();

    expect($subject->coefficient_cp1)->toBeNull()
        ->and($subject->coefficient_cp2)->toBeNull()
        ->and($subject->coefficient_ce1)->toBeNull()
        ->and($subject->coefficient_ce2)->toBeNull()
        ->and((float) $subject->coefficient_cm1)->toBe(1.0)
        ->and($subject->coefficient_cm2)->toBeNull();
});

test('l’abréviation est obligatoire', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('name', 'Matière sans abréviation')
        ->set('abbreviation', '')
        ->call('save')
        ->assertHasErrors(['abbreviation']);

    expect(PrimarySubject::where('name', 'Matière sans abréviation')->exists())->toBeFalse();
});

test('modifier une matière met à jour ses coefficients et barèmes', function () {
    $subject = PrimarySubject::factory()->create(['coefficient_cp1' => 1, 'bareme_cp1' => 20]);

    Livewire::test(Index::class)
        ->call('edit', $subject->id)
        ->assertSet('coefficient_cp1', '1.00')
        ->assertSet('bareme_cp1', '20.00')
        ->set('coefficient_cp1', '4')
        ->set('bareme_cp1', '10')
        ->call('save')
        ->assertHasNoErrors();

    $subject->refresh();

    expect((float) $subject->coefficient_cp1)->toBe(4.0)
        ->and((float) $subject->bareme_cp1)->toBe(10.0);
});

test('supprimer une matière la retire de la liste', function () {
    $subject = PrimarySubject::factory()->create();

    Livewire::test(Index::class)->call('delete', $subject->id);

    expect(PrimarySubject::find($subject->id))->toBeNull();
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});
