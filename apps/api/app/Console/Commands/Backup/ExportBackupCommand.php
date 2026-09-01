<?php

declare(strict_types=1);

namespace App\Console\Commands\Backup;

use App\Domain\Backup\Services\BackupExportService;
use App\Domain\Backup\Services\BackupTableRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportBackupCommand extends Command
{
    protected $signature = 'backup:export {--path= : Chemin de sortie explicite pour l\'archive}';

    protected $description = 'Exporte toutes les tables métier de la plateforme vers une archive .zip de fichiers .xlsx';

    public function handle(BackupTableRegistry $registry, BackupExportService $exporter): int
    {
        $tables = $registry->tables();

        $this->info(sprintf('Export de %d table(s)...', count($tables)));

        $relativePath = $exporter->export($tables, 'cli');

        $absolutePath = Storage::disk((string) config('backup.disk'))->path($relativePath);

        if ($path = $this->option('path')) {
            copy($absolutePath, $path);
            $absolutePath = $path;
        }

        $this->info("Archive générée : {$absolutePath}");

        return self::SUCCESS;
    }
}
