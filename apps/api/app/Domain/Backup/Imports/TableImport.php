<?php

declare(strict_types=1);

namespace App\Domain\Backup\Imports;

use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Lecture brute d'une feuille exportée par TableExport : pas de
 * WithHeadingRow (qui normaliserait les clés d'en-tête et casserait la
 * comparaison stricte ci-dessous), donc la ligne 0 du tableau reçu est
 * l'en-tête. Comparaison stricte des colonnes contre le schéma réel de la
 * table cible avant toute exposition de ligne : n'insère rien elle-même,
 * seulement les lignes lues + le résultat de la comparaison — c'est
 * l'appelant (BackupImportService) qui décide quoi faire d'une dérive.
 */
class TableImport implements ToArray
{
    /** @var list<string> */
    private readonly array $expectedColumns;

    /** @var list<string> */
    private array $actualColumns = [];

    private bool $schemaMatches = false;

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    public function __construct(private readonly string $table)
    {
        $this->expectedColumns = Schema::getColumnListing($table);
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    public function array(array $array): void
    {
        $header = array_map('strval', array_shift($array) ?? []);
        $this->actualColumns = $header;
        $this->schemaMatches = $header === $this->expectedColumns;

        if (! $this->schemaMatches) {
            $this->rows = [];

            return;
        }

        $columnCount = count($header);

        foreach ($array as $row) {
            $row = array_slice(array_pad($row, $columnCount, null), 0, $columnCount);
            $this->rows[] = array_combine($header, $row);
        }
    }

    public function schemaMatches(): bool
    {
        return $this->schemaMatches;
    }

    /**
     * @return list<string>
     */
    public function expectedColumns(): array
    {
        return $this->expectedColumns;
    }

    /**
     * @return list<string>
     */
    public function actualColumns(): array
    {
        return $this->actualColumns;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }
}
