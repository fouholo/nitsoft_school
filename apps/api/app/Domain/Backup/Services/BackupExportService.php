<?php

declare(strict_types=1);

namespace App\Domain\Backup\Services;

use App\Domain\Backup\Exports\TableExport;
use ErrorException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use ZipArchive;

class BackupExportService
{
    public function __construct(private readonly BackupTableRegistry $registry) {}

    /**
     * @param  list<string>  $tables
     * @return string Chemin relatif de l'archive générée sur le disque de config('backup.disk').
     */
    public function export(array $tables, string $author): string
    {
        foreach ($tables as $table) {
            if (! $this->registry->isKnownTable($table)) {
                throw new InvalidArgumentException("Table inconnue ou hors périmètre : {$table}");
            }
        }

        $disk = (string) config('backup.disk');
        $stagingDirectory = trim((string) config('backup.staging_directory'), '/').'/'.(string) Str::uuid();
        $archiveDirectory = trim((string) config('backup.archive_directory'), '/');

        Storage::disk($disk)->makeDirectory($stagingDirectory);
        Storage::disk($disk)->makeDirectory($archiveDirectory);

        $totalRows = 0;

        try {
            foreach ($tables as $table) {
                $rowCount = DB::table($table)->count();

                Excel::store(new TableExport($table), "{$stagingDirectory}/{$table}.xlsx", $disk);

                Log::info('backup.export.table', [
                    'table' => $table,
                    'rows' => $rowCount,
                    'author' => $author,
                ]);

                $totalRows += $rowCount;
            }

            // Suffixe aléatoire : un timestamp seul (précision seconde) peut
            // entrer en collision entre deux exports rapprochés (ex: tests,
            // ou deux admins qui exportent au même instant), provoquant une
            // écriture concurrente sur le même fichier .zip.
            $archiveName = 'backup-'.now()->format('Y-m-d-His').'-'.Str::random(8).'.zip';
            $archiveRelativePath = "{$archiveDirectory}/{$archiveName}";

            $this->assembleArchive($disk, $stagingDirectory, $tables, $archiveRelativePath);
        } finally {
            Storage::disk($disk)->deleteDirectory($stagingDirectory);
        }

        Log::info('backup.export.summary', [
            'tables' => count($tables),
            'rows' => $totalRows,
            'author' => $author,
        ]);

        return $archiveRelativePath;
    }

    /**
     * @param  list<string>  $tables
     */
    private function assembleArchive(string $disk, string $stagingDirectory, array $tables, string $archiveRelativePath): void
    {
        $absoluteArchivePath = Storage::disk($disk)->path($archiveRelativePath);
        $stagingFiles = Storage::disk($disk)->path($stagingDirectory);

        // ZipArchive::close() finalise l'archive en renommant un fichier
        // temporaire par-dessus la destination — un verrou transitoire
        // externe (antivirus, sauvegarde disque concurrente) peut faire
        // échouer ce renommage ponctuellement. Un objet ZipArchive dont le
        // close() a échoué n'est plus réutilisable : on réessaie
        // l'opération complète (ouverture + ajout + fermeture) sur un
        // nouvel objet plutôt que de retenter close() seul.
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $zip = new ZipArchive;

                if ($zip->open($absoluteArchivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException("Impossible de créer l'archive : {$archiveRelativePath}");
                }

                foreach ($tables as $table) {
                    $zip->addFile("{$stagingFiles}/{$table}.xlsx", "{$table}.xlsx");
                }

                if ($zip->close()) {
                    return;
                }

                throw new RuntimeException("Impossible de finaliser l'archive : {$archiveRelativePath}");
            } catch (RuntimeException|ErrorException $e) {
                if ($attempt === $maxAttempts) {
                    throw new RuntimeException("Impossible de finaliser l'archive : {$archiveRelativePath}", previous: $e);
                }

                usleep(250_000);
            }
        }
    }
}
