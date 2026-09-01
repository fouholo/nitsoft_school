<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\SaasAdminType;
use App\Domain\Establishments\Models\SaasAdmin;
use App\Livewire\SaasAdmins\Register;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

test('le premier inscrit devient MAIN et est automatiquement connecté', function () {
    Livewire::test(Register::class)
        ->set('name', 'Premier Admin')
        ->set('first_name', 'Jean')
        ->set('email', 'premier@nitsoft.test')
        ->set('pseudo', 'jpremier')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::where('email', 'premier@nitsoft.test')->sole();
    $saasAdmin = SaasAdmin::where('user_id', $user->id)->sole();

    expect($saasAdmin->type)->toBe(SaasAdminType::Main)
        ->and($saasAdmin->is_main)->toBeTrue()
        ->and($user->first_name)->toBe('Jean')
        ->and($user->pseudo)->toBe('jpremier')
        ->and(auth()->id())->toBe($user->id);
});

test('prénom et pseudo sont obligatoires à l’inscription admin SaaS', function () {
    Livewire::test(Register::class)
        ->set('name', 'Sans Prénom')
        ->set('email', 'sans.prenom@nitsoft.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['first_name', 'pseudo']);
});

test('un pseudo déjà pris est rejeté à l’inscription admin SaaS', function () {
    User::factory()->create(['pseudo' => 'dejapris']);

    Livewire::test(Register::class)
        ->set('name', 'Doublon')
        ->set('first_name', 'Jean')
        ->set('email', 'doublon@nitsoft.test')
        ->set('pseudo', 'dejapris')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['pseudo']);
});

test('la tentative d’inscription alors qu’un MAIN existe déjà redirige vers login sans rien créer', function () {
    createSaasAdmin('main');

    Livewire::test(Register::class)
        ->assertRedirect(route('login'));

    expect(SaasAdmin::where('is_main', true)->count())->toBe(1);
});

test('la contrainte unique is_main empêche un deuxième MAIN au niveau base', function () {
    createSaasAdmin('main');

    $user = User::factory()->create();

    expect(fn () => SaasAdmin::create([
        'user_id' => $user->id,
        'type' => SaasAdminType::Main,
        'is_active' => true,
        'is_main' => true,
    ]))->toThrow(QueryException::class);
});
