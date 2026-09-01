<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;

test('un saas admin secondaire peut télécharger l’export', function () {
    $second = createSaasAdmin('second');
    Establishment::factory()->create();

    $response = $this->actingAs($second)->get(route('backup.export'));

    $response->assertOk();
    $response->assertHeader('content-disposition');
});

test('un saas admin principal peut télécharger l’export', function () {
    $main = createSaasAdmin('main');
    Establishment::factory()->create();

    $response = $this->actingAs($main)->get(route('backup.export'));

    $response->assertOk();
});

test('un utilisateur non saas admin est refusé', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');

    $response = $this->actingAs($directeur)->get(route('backup.export'));

    $response->assertForbidden();
});
