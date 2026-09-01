<?php

declare(strict_types=1);

use App\Domain\Backup\Support\BackupOperation;
use App\Domain\Establishments\Models\Establishment;

test('un saas admin principal peut exporter, vider et restaurer', function () {
    $main = createSaasAdmin('main');

    expect($main->can('export', BackupOperation::class))->toBeTrue()
        ->and($main->can('wipe', BackupOperation::class))->toBeTrue()
        ->and($main->can('import', BackupOperation::class))->toBeTrue();
});

test('un saas admin secondaire peut seulement exporter', function () {
    $second = createSaasAdmin('second');

    expect($second->can('export', BackupOperation::class))->toBeTrue()
        ->and($second->can('wipe', BackupOperation::class))->toBeFalse()
        ->and($second->can('import', BackupOperation::class))->toBeFalse();
});

test('un utilisateur qui n’est pas saas admin ne peut rien de tout ça', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');

    expect($directeur->can('export', BackupOperation::class))->toBeFalse()
        ->and($directeur->can('wipe', BackupOperation::class))->toBeFalse()
        ->and($directeur->can('import', BackupOperation::class))->toBeFalse();
});
