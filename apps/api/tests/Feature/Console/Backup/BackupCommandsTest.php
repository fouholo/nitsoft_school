<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('backup:export produit une archive et l’affiche', function () {
    Establishment::factory()->create();

    $this->artisan('backup:export')
        ->assertExitCode(0)
        ->expectsOutputToContain('Archive générée');
});

test('backup:wipe --force vide les tables ciblées', function () {
    Establishment::factory()->create();

    $this->artisan('backup:wipe', ['--table' => 'establishments', '--force' => true])
        ->assertExitCode(0);

    expect(DB::table('establishments')->count())->toBe(0);
});

test('backup:wipe refuse une table inconnue', function () {
    $this->artisan('backup:wipe', ['--table' => 'sessions', '--force' => true])
        ->assertExitCode(1);
});

test('backup:wipe --force sans --table préserve uid_server_counters', function () {
    $rowsBefore = DB::table('uid_server_counters')->count();

    $this->artisan('backup:wipe', ['--force' => true])
        ->assertExitCode(0);

    expect(DB::table('uid_server_counters')->count())->toBe($rowsBefore);
});

test('backup:wipe --table=uid_server_counters vide quand même la table ciblée explicitement', function () {
    expect(DB::table('uid_server_counters')->count())->toBeGreaterThan(0);

    $this->artisan('backup:wipe', ['--table' => 'uid_server_counters', '--force' => true])
        ->assertExitCode(0);

    expect(DB::table('uid_server_counters')->count())->toBe(0);
});

test('backup:import --dry-run n’écrit rien', function () {
    Establishment::factory()->create();

    $this->artisan('backup:export', ['--path' => storage_path('app/private/probe-cli.zip')])
        ->assertExitCode(0);

    DB::table('establishments')->truncate();

    $this->artisan('backup:import', [
        'archive' => 'probe-cli.zip',
        '--table' => 'establishments',
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(DB::table('establishments')->count())->toBe(0);

    Storage::disk('local')->delete('probe-cli.zip');
});

test('backup:import --force sur une table non vide échoue proprement pour cette table', function () {
    Establishment::factory()->create();

    $this->artisan('backup:export', ['--path' => storage_path('app/private/probe-cli-2.zip')])
        ->assertExitCode(0);

    // La table reste non vide : pas de wipe avant l'import.
    $this->artisan('backup:import', [
        'archive' => 'probe-cli-2.zip',
        '--table' => 'establishments',
        '--force' => true,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('not_empty');

    expect(DB::table('establishments')->count())->toBe(1);

    Storage::disk('local')->delete('probe-cli-2.zip');
});
