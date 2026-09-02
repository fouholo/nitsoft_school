<?php

declare(strict_types=1);

use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Academics\Models\Subject;
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
    $subject = Subject::factory()->create(['name' => 'Mathématiques', 'abbreviation' => 'MATHS', 'is_prescolaire_primaire' => true]);

    Livewire::test(Index::class)
        ->call('create')
        ->set('subject_id', $subject->id)
        ->set('coefficient_cp1', '2')
        ->set('coefficient_cp2', '2')
        ->set('coefficient_cm2', '3')
        ->set('bareme_cp1', '20')
        ->set('bareme_cp2', '20')
        ->set('bareme_cm2', '10')
        ->call('save')
        ->assertHasNoErrors();

    $primarySubject = PrimarySubject::where('subject_id', $subject->id)->sole();

    expect($primarySubject->name)->toBe('Mathématiques')
        ->and($primarySubject->abbreviation)->toBe('MATHS')
        ->and((float) $primarySubject->coefficient_cp1)->toBe(2.0)
        ->and((float) $primarySubject->coefficient_cp2)->toBe(2.0)
        ->and($primarySubject->coefficient_ce1)->toBeNull()
        ->and((float) $primarySubject->coefficient_cm2)->toBe(3.0)
        ->and((float) $primarySubject->bareme_cp1)->toBe(20.0)
        ->and($primarySubject->bareme_ce1)->toBeNull()
        ->and((float) $primarySubject->bareme_cm2)->toBe(10.0)
        ->and($primarySubject->uid_serveur)->toMatch('/^224\d{9}$/');
});

test('un coefficient laissé vide n’est pas configuré pour ce niveau', function () {
    $subject = Subject::factory()->create(['name' => 'Anglais', 'is_prescolaire_primaire' => true]);

    Livewire::test(Index::class)
        ->call('create')
        ->set('subject_id', $subject->id)
        ->set('coefficient_cm1', '1')
        ->call('save')
        ->assertHasNoErrors();

    $primarySubject = PrimarySubject::where('subject_id', $subject->id)->sole();

    expect($primarySubject->coefficient_cp1)->toBeNull()
        ->and($primarySubject->coefficient_cp2)->toBeNull()
        ->and($primarySubject->coefficient_ce1)->toBeNull()
        ->and($primarySubject->coefficient_ce2)->toBeNull()
        ->and((float) $primarySubject->coefficient_cm1)->toBe(1.0)
        ->and($primarySubject->coefficient_cm2)->toBeNull();
});

test('la matière est obligatoire', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('subject_id', null)
        ->call('save')
        ->assertHasErrors(['subject_id']);

    expect(PrimarySubject::count())->toBe(0);
});

test('une même matière ne peut pas être configurée deux fois pour le primaire', function () {
    $subject = Subject::factory()->create(['is_prescolaire_primaire' => true]);
    PrimarySubject::factory()->create(['subject_id' => $subject->id]);

    Livewire::test(Index::class)
        ->call('create')
        ->set('subject_id', $subject->id)
        ->call('save')
        ->assertHasErrors(['subject_id']);
});

test('la liste déroulante ne propose que les matières déjà présentes dans le catalogue et pas déjà configurées', function () {
    $availableSubject = Subject::factory()->create(['name' => 'Disponible', 'is_prescolaire_primaire' => true]);
    $secondaireOnlySubject = Subject::factory()->create(['name' => 'Philosophie', 'is_prescolaire_primaire' => false, 'is_secondaire' => true]);
    $alreadyConfiguredSubject = Subject::factory()->create(['name' => 'Déjà configurée', 'is_prescolaire_primaire' => true]);
    PrimarySubject::factory()->create(['subject_id' => $alreadyConfiguredSubject->id]);

    Livewire::test(Index::class)
        ->call('create')
        ->assertSee("<option value=\"{$availableSubject->id}\">Disponible</option>", false)
        ->assertDontSee("<option value=\"{$secondaireOnlySubject->id}\">Philosophie</option>", false)
        ->assertDontSee("<option value=\"{$alreadyConfiguredSubject->id}\">Déjà configurée</option>", false);
});

test('modifier une matière garde sa propre matière disponible dans la liste et met à jour ses coefficients et barèmes', function () {
    $subject = Subject::factory()->create(['name' => 'Modifiable', 'is_prescolaire_primaire' => true]);
    $primarySubject = PrimarySubject::factory()->create(['subject_id' => $subject->id, 'coefficient_cp1' => 1, 'bareme_cp1' => 20]);

    Livewire::test(Index::class)
        ->call('edit', $primarySubject->id)
        ->assertSet('subject_id', $subject->id)
        ->assertSet('coefficient_cp1', '1.00')
        ->assertSet('bareme_cp1', '20.00')
        ->assertSee('Modifiable', false)
        ->set('coefficient_cp1', '4')
        ->set('bareme_cp1', '10')
        ->call('save')
        ->assertHasNoErrors();

    $primarySubject->refresh();

    expect((float) $primarySubject->coefficient_cp1)->toBe(4.0)
        ->and((float) $primarySubject->bareme_cp1)->toBe(10.0);
});

test('supprimer une matière la retire de la liste', function () {
    $primarySubject = PrimarySubject::factory()->create();

    Livewire::test(Index::class)->call('delete', $primarySubject->id);

    expect(PrimarySubject::find($primarySubject->id))->toBeNull();
});

test('un directeur d’établissement ne peut pas accéder à l’écran', function () {
    $admin = createUserWithRole($this->establishment, 'directeur');
    $this->actingAs($admin);

    Livewire::test(Index::class)->assertForbidden();
});
