<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;

test('le layout invité rend dir="rtl" en arabe et dir="ltr" en français', function () {
    session(['locale' => 'ar']);
    $this->get('/login')->assertSee('dir="rtl"', false);

    session(['locale' => 'fr']);
    $this->get('/login')->assertSee('dir="ltr"', false);
});

test('le layout app rend dir="rtl" en arabe pour un utilisateur connecté', function () {
    $establishment = Establishment::factory()->create();
    $user = createUserWithRole($establishment, 'directeur');
    $user->update(['locale' => 'ar']);
    actingInEstablishment($establishment);
    $this->actingAs($user);

    $this->get(route('dashboard'))->assertSee('dir="rtl"', false);
});
