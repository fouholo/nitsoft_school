<?php

declare(strict_types=1);

namespace App\Console\Commands\Backup;

use App\Domain\Backup\Services\BackupImportService;
use App\Domain\Backup\Services\BackupTableRegistry;
use Illuminate\Console\Command;

class ImportBackupCommand extends Command
{
    protected $signature = 'backup:import
        {archive : Chemin de l\'archive .zip, relatif au disque de configuration}
        {--table= : Ne restaurer qu\'une seule table}
        {--force : Ne pas demander de confirmation}
        {--dry-run : Simuler sans rien écrire}';

    protected $description = 'Restaure les tables métier de la plateforme depuis une archive de sauvegarde';

    public function handle(BackupTableRegistry $registry, BackupImportService $importer): int
    {
        $archive = (string) $this->argument('archive');
        $requestedTable = $this->option('table');

        if ($requestedTable !== null && ! $registry->isKnownTable($requestedTable)) {
            $this->error("Table inconnue ou hors périmètre : {$requestedTable}");

            return self::FAILURE;
        }

        $tables = $requestedTable !== null ? [$requestedTable] : $registry->tables();

        if ($this->option('dry-run')) {
            $result = $importer->plan($archive, $tables);
            $this->displayResult($result, dryRun: true);

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                sprintf('Restaurer %d table(s) depuis %s ? Les tables non vides seront refusées individuellement.', count($tables), $archive)
            );

            if (! $confirmed) {
                $this->warn('Annulé.');

                return self::SUCCESS;
            }
        }

        $result = $importer->import($archive, $tables, 'cli');
        $this->displayResult($result, dryRun: false);

        return self::SUCCESS;
    }

    /**
     * @param  array{tables: array<string, array<string, mixed>>, orphans: list<string>}  $result
     */
    private function displayResult(array $result, bool $dryRun): void
    {
        foreach ($result['tables'] as $table => $status) {
            $label = $dryRun ? ($status['status'] === 'ok' ? 'prêt' : $status['status']) : $status['status'];
            $rows = $status['rows'] ?? '?';
            $this->line("{$table} : {$label} ({$rows} ligne(s))");
        }

        if ($result['orphans'] !== []) {
            $this->warn('Fichiers ignorés (aucune table correspondante) : '.implode(', ', $result['orphans']));
        }
    }
}
