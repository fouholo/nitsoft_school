<?php

declare(strict_types=1);

namespace App\Console\Commands\Backup;

use App\Domain\Backup\Services\BackupTableRegistry;
use App\Domain\Backup\Services\BackupWipeService;
use Illuminate\Console\Command;

class WipeBackupCommand extends Command
{
    protected $signature = 'backup:wipe {--table= : Ne vider qu\'une seule table} {--force : Ne pas demander de confirmation}';

    protected $description = 'Vide les tables métier de la plateforme (destructif)';

    public function handle(BackupTableRegistry $registry, BackupWipeService $wiper): int
    {
        $requestedTable = $this->option('table');

        if ($requestedTable !== null && ! $registry->isKnownTable($requestedTable)) {
            $this->error("Table inconnue ou hors périmètre : {$requestedTable}");

            return self::FAILURE;
        }

        $tables = $requestedTable !== null ? [$requestedTable] : $registry->wipeableTables();

        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                sprintf('Vider %d table(s) (%s) ? Cette action est irréversible.', count($tables), implode(', ', $tables))
            );

            if (! $confirmed) {
                $this->warn('Annulé.');

                return self::SUCCESS;
            }
        }

        $counts = $wiper->wipe($tables, 'cli');

        foreach ($counts as $table => $rowsDeleted) {
            $this->line("{$table} : {$rowsDeleted} ligne(s) supprimée(s)");
        }

        return self::SUCCESS;
    }
}
