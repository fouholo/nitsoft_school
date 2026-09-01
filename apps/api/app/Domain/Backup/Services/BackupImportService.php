<?php

declare(strict_types=1);

namespace App\Domain\Backup\Services;

use App\Domain\Backup\Imports\TableImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use ZipArchive;

class BackupImportService
{
    public function __construct(private readonly BackupTableRegistry $registry) {}

    /**
     * Simule une restauration sans rien écrire.
     *
     * @param  list<string>  $tables
     * @return array{tables: array<string, array<string, mixed>>, orphans: list<string>}
     */
    public function plan(string $zipRelativePath, array $tables): array
    {
        return $this->process($zipRelativePath, $tables, dryRun: true, author: 'dry-run');
    }

    /**
     * Restaure réellement les tables demandées présentes dans l'archive.
     * Chaque table est traitée dans sa propre transaction : un échec sur
     * une table (non vide, ou dérive de schéma) n'empêche pas les autres
     * tables de l'archive d'être traitées.
     *
     * @param  list<string>  $tables
     * @return array{tables: array<string, array<string, mixed>>, orphans: list<string>}
     */
    public function import(string $zipRelativePath, array $tables, string $author): array
    {
        return $this->process($zipRelativePath, $tables, dryRun: false, author: $author);
    }

    /**
     * @param  list<string>  $tables
     * @return array{tables: array<string, array<string, mixed>>, orphans: list<string>}
     */
    private function process(string $zipRelativePath, array $tables, bool $dryRun, string $author): array
    {
        $disk = (string) config('backup.disk');
        $absolutePath = Storage::disk($disk)->path($zipRelativePath);

        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException("Impossible d'ouvrir l'archive : {$zipRelativePath}");
        }

        // Allowlist de sécurité : seules les tables reconnues du périmètre
        // actuel sont considérées, quel que soit ce que demande l'appelant.
        $requestedTables = array_values(array_filter(
            $tables,
            fn (string $table): bool => $this->registry->isKnownTable($table)
        ));

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }

        $matchedFiles = array_map(fn (string $table): string => "{$table}.xlsx", $requestedTables);
        $orphans = array_values(array_diff($entries, $matchedFiles));

        $results = [];

        if (! $dryRun) {
            Schema::disableForeignKeyConstraints();
        }

        try {
            foreach ($requestedTables as $table) {
                $filename = "{$table}.xlsx";

                if (! in_array($filename, $entries, true)) {
                    $results[$table] = ['status' => 'missing_in_archive', 'rows' => 0];

                    continue;
                }

                $results[$table] = $this->processTable($zip, $table, $dryRun, $author);
            }
        } finally {
            if (! $dryRun) {
                Schema::enableForeignKeyConstraints();
            }
        }

        $zip->close();

        if ($orphans !== []) {
            Log::warning('backup.import.orphans', ['files' => $orphans, 'author' => $author]);
        }

        return ['tables' => $results, 'orphans' => $orphans];
    }

    /**
     * @return array<string, mixed>
     */
    private function processTable(ZipArchive $zip, string $table, bool $dryRun, string $author): array
    {
        $contents = $zip->getFromName("{$table}.xlsx");
        $tempPath = sys_get_temp_dir().'/backup_import_'.Str::random(20).'.xlsx';
        file_put_contents($tempPath, $contents);

        try {
            $import = new TableImport($table);
            Excel::import($import, $tempPath);

            if (! $import->schemaMatches()) {
                Log::warning('backup.import.schema_drift', [
                    'table' => $table,
                    'expected' => $import->expectedColumns(),
                    'actual' => $import->actualColumns(),
                    'author' => $author,
                ]);

                return [
                    'status' => 'schema_drift',
                    'expected_columns' => $import->expectedColumns(),
                    'actual_columns' => $import->actualColumns(),
                ];
            }

            $rows = $import->rows();
            $rowCount = count($rows);

            if (DB::table($table)->exists()) {
                Log::warning('backup.import.not_empty', ['table' => $table, 'author' => $author]);

                return ['status' => 'not_empty', 'rows' => $rowCount];
            }

            if ($dryRun) {
                return ['status' => 'ok', 'rows' => $rowCount];
            }

            DB::transaction(function () use ($table, $rows): void {
                foreach (array_chunk($rows, (int) config('backup.insert_chunk_size')) as $chunk) {
                    DB::table($table)->insert($chunk);
                }

                if (DB::getDriverName() === 'mysql' && in_array('id', Schema::getColumnListing($table), true)) {
                    $nextId = (int) DB::table($table)->max('id') + 1;
                    DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$nextId}");
                }
            });

            Log::info('backup.import.table', ['table' => $table, 'rows' => $rowCount, 'author' => $author]);

            return ['status' => 'imported', 'rows' => $rowCount];
        } finally {
            @unlink($tempPath);
        }
    }
}
