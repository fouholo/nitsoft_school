<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\SaasAdminType;
use App\Domain\Establishments\Models\SaasAdmin;
use App\Livewire\SaasAdmins\Index;
use App\Models\User;
use Livewire\Livewire;

test('MAIN peut créer un administrateur SECOND', function () {
    $main = createSaasAdmin('main');
    $this->actingAs($main);

    Livewire::test(Index::class)
        ->set('admin_name', 'Admin Secondaire')
        ->set('admin_first_name', 'Marie')
        ->set('admin_email', 'second@nitsoft.test')
        ->set('admin_pseudo', 'msecond')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSet('generatedPasswordFor', 'second@nitsoft.test');

    $user = User::where('email', 'second@nitsoft.test')->sole();
    $saasAdmin = SaasAdmin::where('user_id', $user->id)->sole();

    expect($saasAdmin->type)->toBe(SaasAdminType::Second)
        ->and($saasAdmin->is_active)->toBeTrue()
        ->and($user->first_name)->toBe('Marie')
        ->and($user->pseudo)->toBe('msecond');
});

test('prénom et pseudo sont obligatoires à la création d’un admin SaaS', function () {
    $main = createSaasAdmin('main');
    $this->actingAs($main);

    Livewire::test(Index::class)
        ->set('admin_name', 'Sans Prénom')
        ->set('admin_email', 'sans.prenom@nitsoft.test')
        ->call('create')
        ->assertHasErrors(['admin_first_name', 'admin_pseudo']);
});

test('un pseudo déjà pris est rejeté à la création d’un admin SaaS', function () {
    $main = createSaasAdmin('main');
    $this->actingAs($main);
    User::factory()->create(['pseudo' => 'dejapris']);

    Livewire::test(Index::class)
        ->set('admin_name', 'Doublon')
        ->set('admin_first_name', 'Jean')
        ->set('admin_email', 'doublon@nitsoft.test')
        ->set('admin_pseudo', 'dejapris')
        ->call('create')
        ->assertHasErrors(['admin_pseudo']);
});

test('SECOND ne peut pas créer, modifier ou supprimer d’administrateurs', function () {
    $second = createSaasAdmin('second');
    $this->actingAs($second);

    $target = createSaasAdmin('second');

    Livewire::test(Index::class)
        ->set('admin_name', 'Peu importe')
        ->set('admin_email', 'peu.importe@nitsoft.test')
        ->call('create')
        ->assertForbidden();

    Livewire::test(Index::class)
        ->call('deactivate', SaasAdmin::where('user_id', $target->id)->sole()->id)
        ->assertForbidden();

    Livewire::test(Index::class)
        ->call('delete', SaasAdmin::where('user_id', $target->id)->sole()->id)
        ->assertForbidden();
});

test('MAIN ne peut pas se désactiver ni se supprimer lui-même', function () {
    $main = createSaasAdmin('main');
    $this->actingAs($main);

    $mainSaasAdmin = SaasAdmin::where('user_id', $main->id)->sole();

    Livewire::test(Index::class)
        ->call('deactivate', $mainSaasAdmin->id)
        ->assertStatus(422);

    Livewire::test(Index::class)
        ->call('delete', $mainSaasAdmin->id)
        ->assertStatus(422);
});

test('le prénom d’un administrateur SaaS est affiché dans le tableau', function () {
    $main = createSaasAdmin('main');
    $this->actingAs($main);

    $second = User::factory()->create(['name' => 'Dupont', 'first_name' => 'Jean']);
    SaasAdmin::create(['user_id' => $second->id, 'type' => SaasAdminType::Second, 'is_active' => true]);

    Livewire::test(Index::class)
        ->assertSee('Jean Dupont');
});

test('désactiver un SECOND lui retire immédiatement le bypass Gate::before', function () {
    $main = createSaasAdmin('main');
    $second = createSaasAdmin('second');
    $this->actingAs($main);

    $secondSaasAdmin = SaasAdmin::where('user_id', $second->id)->sole();

    Livewire::test(Index::class)->call('deactivate', $secondSaasAdmin->id);

    expect($second->fresh()->isSaasAdmin())->toBeFalse();
});
