<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Backup\Exports\TableExport;
use App\Domain\Backup\Services\BackupExportService;
use App\Domain\Backup\Services\BackupImportService;
use App\Domain\Backup\Services\BackupTableRegistry;
use App\Domain\Backup\Services\BackupWipeService;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->registry = new BackupTableRegistry;
    $this->exporter = new BackupExportService($this->registry);
    $this->wiper = new BackupWipeService($this->registry);
    $this->importer = new BackupImportService($this->registry);
});

test('round-trip sur une table simple préserve les IDs et corrige le prochain auto-increment', function () {
    $establishment = Establishment::factory()->create();
    $originalId = $establishment->id;

    $zipPath = $this->exporter->export(['establishments'], 'test');
    $this->wiper->wipe(['establishments'], 'test');

    expect(DB::table('establishments')->count())->toBe(0);

    $result = $this->importer->import($zipPath, ['establishments'], 'test');

    expect($result['tables']['establishments']['status'])->toBe('imported')
        ->and(DB::table('establishments')->pluck('id')->all())->toBe([$originalId]);

    $newEstablishment = Establishment::factory()->create();

    expect($newEstablishment->id)->toBeGreaterThan($originalId);
});

test('round-trip préserve la clé étrangère vers la table parente', function () {
    $establishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->create([
        'establishment_id' => $establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);

    $zipPath = $this->exporter->export(['establishments', 'school_years', 'classrooms'], 'test');
    $this->wiper->wipe(['classrooms'], 'test');

    $result = $this->importer->import($zipPath, ['classrooms'], 'test');

    expect($result['tables']['classrooms']['status'])->toBe('imported');

    $restored = DB::table('classrooms')->where('id', $classroom->id)->sole();

    expect((int) $restored->establishment_id)->toBe($establishment->id)
        ->and((int) $restored->school_year_id)->toBe($schoolYear->id);
});

test('round-trip sur une table sans colonne id (composite) fonctionne sans erreur', function () {
    Schema::create('backup_pivot_probe', function (Blueprint $table): void {
        $table->unsignedBigInteger('left_id');
        $table->unsignedBigInteger('right_id');
        $table->boolean('flag')->default(false);
        $table->primary(['left_id', 'right_id']);
    });

    DB::table('backup_pivot_probe')->insert([
        ['left_id' => 1, 'right_id' => 2, 'flag' => true],
        ['left_id' => 1, 'right_id' => 3, 'flag' => false],
    ]);

    $exporter = new BackupExportService($this->registry);
    $zipPath = $exporter->export(['backup_pivot_probe'], 'test');

    DB::table('backup_pivot_probe')->truncate();

    $result = $this->importer->import($zipPath, ['backup_pivot_probe'], 'test');

    expect($result['tables']['backup_pivot_probe']['status'])->toBe('imported')
        ->and(DB::table('backup_pivot_probe')->count())->toBe(2);

    Schema::dropIfExists('backup_pivot_probe');
});

test('refuse d’importer dans une table non vide sans bloquer les autres tables de l’archive', function () {
    $keepEstablishment = Establishment::factory()->create();
    $schoolYear = SchoolYear::factory()->create();

    $zipPath = $this->exporter->export(['establishments', 'school_years'], 'test');

    // establishments reste non vide (pas de wipe) ; school_years est vidée.
    $this->wiper->wipe(['school_years'], 'test');

    $result = $this->importer->import($zipPath, ['establishments', 'school_years'], 'test');

    expect($result['tables']['establishments']['status'])->toBe('not_empty')
        ->and($result['tables']['school_years']['status'])->toBe('imported')
        ->and(DB::table('establishments')->count())->toBe(1)
        ->and(DB::table('school_years')->count())->toBe(1);
});

test('détecte une dérive de schéma et n’insère aucune ligne pour la table concernée', function () {
    Establishment::factory()->create();

    $zipPath = $this->exporter->export(['establishments'], 'test');

    $this->wiper->wipe(['establishments'], 'test');

    // Simule une dérive de schéma : ajoute une colonne après l'export.
    Schema::table('establishments', function (Blueprint $table): void {
        $table->string('champ_ajoute_apres_export')->nullable();
    });

    $result = $this->importer->import($zipPath, ['establishments'], 'test');

    expect($result['tables']['establishments']['status'])->toBe('schema_drift')
        ->and(DB::table('establishments')->count())->toBe(0);

    Schema::table('establishments', function (Blueprint $table): void {
        $table->dropColumn('champ_ajoute_apres_export');
    });
});

test('ignore un fichier orphelin dans l’archive sans bloquer les autres tables', function () {
    Establishment::factory()->create();

    Storage::disk('local')->makeDirectory('orphan-test');
    Excel::store(new TableExport('establishments'), 'orphan-test/establishments.xlsx', 'local');

    $archiveRelativePath = 'orphan-test/archive.zip';
    $absolutePath = Storage::disk('local')->path($archiveRelativePath);

    $zip = new ZipArchive;
    $zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFile(Storage::disk('local')->path('orphan-test/establishments.xlsx'), 'establishments.xlsx');
    $zip->addFromString('table_qui_nexiste_pas.xlsx', 'contenu quelconque');
    $zip->close();

    $this->wiper->wipe(['establishments'], 'test');

    $result = $this->importer->import($archiveRelativePath, ['establishments'], 'test');

    expect($result['tables']['establishments']['status'])->toBe('imported')
        ->and($result['orphans'])->toContain('table_qui_nexiste_pas.xlsx');
});
