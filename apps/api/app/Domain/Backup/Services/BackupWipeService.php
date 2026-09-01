<?php

declare(strict_types=1);

namespace App\Domain\Backup\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class BackupWipeService
{
    public function __construct(private readonly BackupTableRegistry $registry) {}

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    public function wipe(array $tables, string $author): array
    {
        foreach ($tables as $table) {
            if (! $this->registry->isKnownTable($table)) {
                throw new InvalidArgumentException("Table inconnue ou hors périmètre : {$table}");
            }
        }

        $counts = [];

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                $counts[$table] = DB::table($table)->count();

                DB::table($table)->truncate();

                Log::info('backup.wipe.table', [
                    'table' => $table,
                    'rows_deleted' => $counts[$table],
                    'author' => $author,
                ]);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        Log::info('backup.wipe.summary', [
            'tables' => count($tables),
            'rows_deleted' => array_sum($counts),
            'author' => $author,
        ]);

        return $counts;
    }
}
