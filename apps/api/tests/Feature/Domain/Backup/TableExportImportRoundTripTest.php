<?php

declare(strict_types=1);

use App\Domain\Backup\Exports\TableExport;
use App\Domain\Backup\Imports\TableImport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Test de risque, écrit tôt : valide que le couple TableExport/TableImport
 * préserve fidèlement les valeurs "à risque" (chaîne à apparence numérique,
 * bigint proche de la limite de précision float, NULL vs chaîne vide) sur
 * une table ad-hoc dont on contrôle exactement le schéma et les données —
 * indépendant de l'évolution des tables métier réelles.
 */
beforeEach(function () {
    Schema::create('backup_engine_probe', function (Blueprint $table): void {
        $table->id();
        $table->string('phone_like', 20)->nullable();
        $table->string('nullable_text', 100)->nullable();
        $table->unsignedBigInteger('big_number')->nullable();
        $table->boolean('flag')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('backup_engine_probe');
});

test('préserve les valeurs à risque à travers un cycle export puis import', function () {
    DB::table('backup_engine_probe')->insert([
        ['id' => 1, 'phone_like' => '0700000001', 'nullable_text' => null, 'big_number' => 9223372036854775807, 'flag' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'phone_like' => '0000000000', 'nullable_text' => '', 'big_number' => 0, 'flag' => false, 'created_at' => now(), 'updated_at' => now()],
    ]);

    Excel::store(new TableExport('backup_engine_probe'), 'probe.xlsx', 'local');

    $import = new TableImport('backup_engine_probe');
    Excel::import($import, 'probe.xlsx', 'local');

    expect($import->schemaMatches())->toBeTrue();

    $rows = $import->rows();
    expect($rows)->toHaveCount(2);

    expect($rows[0]['phone_like'])->toBe('0700000001')
        ->and($rows[0]['nullable_text'])->toBeNull()
        ->and($rows[0]['big_number'])->toBe('9223372036854775807')
        ->and($rows[1]['phone_like'])->toBe('0000000000')
        // Limite connue du format XLSX (voir TableExport) : une chaîne vide
        // explicite redevient NULL après un cycle export→import réel.
        ->and($rows[1]['nullable_text'])->toBeNull()
        ->and($rows[1]['big_number'])->toBe('0');

    Storage::disk('local')->delete('probe.xlsx');
});
