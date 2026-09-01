<?php

declare(strict_types=1);

namespace App\Domain\Backup\Services;

use Illuminate\Support\Facades\Schema;

class BackupTableRegistry
{
    /**
     * @return list<string>
     */
    public function tables(): array
    {
        $excluded = array_flip(config('backup.excluded_tables', []));

        // Schema::getTables() sans argument retourne les tables de TOUTES
        // les bases visibles par la connexion (comportement du grammar
        // MySQL) — un serveur mutualisé peut héberger d'autres bases que
        // celle de l'application. On scope explicitement au schéma courant.
        $tables = array_map(
            static fn (array $table): string => $table['name'],
            Schema::getTables(Schema::getCurrentSchemaName())
        );

        $tables = array_values(array_filter(
            $tables,
            static fn (string $table): bool => ! isset($excluded[$table])
        ));

        sort($tables);

        return $tables;
    }

    public function isKnownTable(string $table): bool
    {
        return in_array($table, $this->tables(), true);
    }
}
