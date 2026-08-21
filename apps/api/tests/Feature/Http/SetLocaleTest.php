<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;

test('un visiteur non connecté qui change de langue via la route la stocke en session', function () {
    $this->get(route('locale.switch', 'en'))->assertRedirect();

    expect(session('locale'))->toBe('en')
        ->and(app()->getLocale())->toBe('fr');

    $this->get('/login');

    expect(app()->getLocale())->toBe('en');
});

test('un utilisateur connecté qui change de langue met à jour User.locale en base', function () {
    $establishment = Establishment::factory()->create();
    $user = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($user);

    $this->get(route('locale.switch', 'ar'))->assertRedirect();

    expect($user->fresh()->locale)->toBe('ar');

    $this->get(route('dashboard'));

    expect(app()->getLocale())->toBe('ar');
});

test('une locale hors whitelist est refusée', function () {
    $this->get(route('locale.switch', 'de'))->assertNotFound();
});

test('la préférence User.locale prévaut sur la session pour un utilisateur connecté', function () {
    $establishment = Establishment::factory()->create();
    $user = createUserWithRole($establishment, 'directeur');
    $user->update(['locale' => 'en']);
    actingInEstablishment($establishment);
    session(['locale' => 'ar']);
    $this->actingAs($user);

    $this->get(route('dashboard'));

    expect(app()->getLocale())->toBe('en');
});

test('un utilisateur sans locale explicite retombe sur la locale par défaut', function () {
    $establishment = Establishment::factory()->create();
    $user = createUserWithRole($establishment, 'directeur');
    expect($user->locale)->toBeNull();
    actingInEstablishment($establishment);
    $this->actingAs($user);

    $this->get(route('dashboard'));

    expect(app()->getLocale())->toBe(config('app.locale'));
});
