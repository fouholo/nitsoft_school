<?php

declare(strict_types=1);

use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Account\ChangePassword;
use App\Livewire\Auth\Login;
use App\Livewire\GuardianPortal\LinkChild;
use App\Livewire\Staff\Index as StaffIndex;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('un compte créé par un administrateur garde le mot de passe par défaut mais doit le changer', function () {
    $establishment = Establishment::factory()->create(['foundation_id' => null]);
    $admin = createGeneralAdmin($establishment);
    actingInEstablishment($establishment);
    $this->actingAs($admin);

    Livewire::test(StaffIndex::class, ['establishment' => $establishment])
        ->set('staff_name', 'Koné')
        ->set('staff_first_name', 'Awa')
        ->set('staff_email', 'awa.kone@example.test')
        ->set('staff_pseudo', 'akone')
        ->set('staff_role', 'caissier')
        ->call('create')
        ->assertHasNoErrors();

    $created = User::where('email', 'awa.kone@example.test')->sole();

    expect($created->must_change_password)->toBeTrue()
        ->and(Hash::check(User::DEFAULT_PASSWORD, $created->password))->toBeTrue();
});

test('tant que le mot de passe par défaut n’est pas changé, toute page ramène à son changement', function () {
    $establishment = Establishment::factory()->create();
    $user = createUserWithRole($establishment, 'directeur');
    $user->update(['must_change_password' => true]);

    $this->actingAs($user);

    $this->get(route('dashboard'))->assertRedirect(route('account.password.edit'));
    $this->get(route('students.index'))->assertRedirect(route('account.password.edit'));
    $this->get(route('account.password.edit'))->assertOk()->assertSee(__('Choisissez votre mot de passe pour continuer.'));
});

test('changer le mot de passe lève l’obligation et rouvre l’application', function () {
    $establishment = Establishment::factory()->create();
    $user = createUserWithRole($establishment, 'directeur');
    $user->update(['password' => User::DEFAULT_PASSWORD, 'must_change_password' => true]);
    actingInEstablishment($establishment);
    $this->actingAs($user);

    Livewire::test(ChangePassword::class)
        ->set('current_password', User::DEFAULT_PASSWORD)
        ->set('password', 'mon-nouveau-secret')
        ->set('password_confirmation', 'mon-nouveau-secret')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect($user->fresh()->must_change_password)->toBeFalse();

    $this->get(route('dashboard'))->assertOk();
});

test('le mot de passe par défaut ne peut pas être choisi à nouveau', function () {
    $establishment = Establishment::factory()->create();
    $user = createUserWithRole($establishment, 'directeur');
    $user->update(['password' => 'ancien-secret', 'must_change_password' => true]);
    actingInEstablishment($establishment);
    $this->actingAs($user);

    Livewire::test(ChangePassword::class)
        ->set('current_password', 'ancien-secret')
        ->set('password', User::DEFAULT_PASSWORD)
        ->set('password_confirmation', User::DEFAULT_PASSWORD)
        ->call('save')
        ->assertHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('un compte sans obligation n’est pas redirigé', function () {
    $establishment = Establishment::factory()->create();
    $this->actingAs(createUserWithRole($establishment, 'directeur'));

    $this->get(route('dashboard'))->assertOk();
});

test('la connexion se bloque après cinq échecs, même avec le bon mot de passe ensuite', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    foreach (range(1, 5) as $attempt) {
        Livewire::test(Login::class)
            ->set('identifiant', $user->email)
            ->set('password', 'mauvais-'.$attempt)
            ->call('login')
            ->assertHasErrors('identifiant');
    }

    Livewire::test(Login::class)
        ->set('identifiant', $user->email)
        ->set('password', 'secret-password')
        ->call('login')
        ->assertHasErrors('identifiant')
        ->assertNoRedirect();

    $this->assertGuest();
});

test('une connexion réussie remet le compteur d’échecs à zéro', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    foreach (range(1, 4) as $attempt) {
        Livewire::test(Login::class)->set('identifiant', $user->email)->set('password', 'mauvais')->call('login');
    }

    Livewire::test(Login::class)
        ->set('identifiant', $user->email)
        ->set('password', 'secret-password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    auth()->logout();

    Livewire::test(Login::class)
        ->set('identifiant', $user->email)
        ->set('password', 'secret-password')
        ->call('login')
        ->assertHasNoErrors();
});

test('la recherche d’élève du portail parents est plafonnée', function () {
    $parent = User::factory()->create();
    Guardian::factory()->create(['user_id' => $parent->id]);
    $student = Student::factory()->create(['establishment_id' => Establishment::factory()->create()->id]);
    $this->actingAs($parent);

    foreach (range(1, 10) as $attempt) {
        Livewire::test(LinkChild::class)->set('uid', '999999999999')->call('search')->assertHasErrors('uid');
    }

    Livewire::test(LinkChild::class)
        ->set('uid', $student->uid_serveur)
        ->call('search')
        ->assertHasErrors('uid')
        ->assertSet('foundStudent', null);
});

test('les réponses portent les en-têtes de sécurité', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();

    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});
