<?php

declare(strict_types=1);

use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;

test('un membre du personnel authentifié reçoit le manuel du personnel', function () {
    $establishment = Establishment::factory()->create();
    $user = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $this->actingAs($user)
        ->get(route('manual.staff'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('un parent authentifié reçoit le manuel des parents', function () {
    $user = User::factory()->create();
    Guardian::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('guardian-portal.manual'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('un visiteur non authentifié est redirigé vers la connexion', function () {
    $this->get(route('manual.staff'))->assertRedirect(route('login'));
    $this->get(route('guardian-portal.manual'))->assertRedirect(route('login'));
});
