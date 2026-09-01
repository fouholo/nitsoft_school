<?php

declare(strict_types=1);

use App\Domain\Backup\Services\BackupTableRegistry;

test('exclut les tables techniques Laravel', function () {
    $tables = (new BackupTableRegistry)->tables();

    expect($tables)->not->toContain('cache')
        ->and($tables)->not->toContain('cache_locks')
        ->and($tables)->not->toContain('jobs')
        ->and($tables)->not->toContain('failed_jobs')
        ->and($tables)->not->toContain('job_batches')
        ->and($tables)->not->toContain('sessions')
        ->and($tables)->not->toContain('password_reset_tokens')
        ->and($tables)->not->toContain('personal_access_tokens')
        ->and($tables)->not->toContain('migrations');
});

test('inclut les tables métier connues', function () {
    $tables = (new BackupTableRegistry)->tables();

    expect($tables)->toContain('students')
        ->and($tables)->toContain('establishments')
        ->and($tables)->toContain('users');
});

test('isKnownTable reconnaît une table du périmètre et rejette le reste', function () {
    $registry = new BackupTableRegistry;

    expect($registry->isKnownTable('students'))->toBeTrue()
        ->and($registry->isKnownTable('sessions'))->toBeFalse()
        ->and($registry->isKnownTable('table_qui_nexiste_pas'))->toBeFalse();
});
