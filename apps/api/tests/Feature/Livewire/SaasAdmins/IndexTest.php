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
        ->set('admin_email', 'second@nitsoft.test')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSet('generatedPasswordFor', 'second@nitsoft.test');

    $user = User::where('email', 'second@nitsoft.test')->sole();
    $saasAdmin = SaasAdmin::where('user_id', $user->id)->sole();

    expect($saasAdmin->type)->toBe(SaasAdminType::Second)
        ->and($saasAdmin->is_active)->toBeTrue();
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

test('désactiver un SECOND lui retire immédiatement le bypass Gate::before', function () {
    $main = createSaasAdmin('main');
    $second = createSaasAdmin('second');
    $this->actingAs($main);

    $secondSaasAdmin = SaasAdmin::where('user_id', $second->id)->sole();

    Livewire::test(Index::class)->call('deactivate', $secondSaasAdmin->id);

    expect($second->fresh()->isSaasAdmin())->toBeFalse();
});
