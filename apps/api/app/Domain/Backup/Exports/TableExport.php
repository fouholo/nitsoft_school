<?php

declare(strict_types=1);

namespace App\Domain\Backup\Exports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Export brut d'une table, colonnes explicites du schéma réel (jamais de
 * SELECT * implicite). Hérite de StringValueBinder (fournit bindValue()
 * pour WithCustomValueBinder) : chaque valeur écrite est forcée en type
 * "chaîne" Excel, sinon PhpSpreadsheet réinterprète les chaînes à
 * apparence numérique (téléphones à zéro non significatif, bigint proches
 * de la limite de précision float) en nombres et corrompt la donnée.
 * WithStrictNullComparison force en plus une comparaison stricte (===) au
 * lieu d'une comparaison lâche (==) lors de l'écriture des cellules :
 * sans elle, PhpSpreadsheet traiterait aussi 0 et false comme "null"
 * (égalité lâche PHP) et les cellules correspondantes resteraient vides.
 *
 * Limite connue et acceptée : une chaîne vide explicite ('') en base
 * redevient NULL après un cycle export→import. Ce n'est pas un choix de
 * ce moteur mais une limite du format XLSX lui-même — vérifié
 * empiriquement, une cellule Excel écrite avec la valeur '' est
 * indiscernable d'une cellule jamais écrite dès qu'elle est relue depuis
 * un fichier .xlsx réellement sauvegardé sur disque (PhpSpreadsheet ne
 * fait pas la distinction à la relecture, quel que soit le réglage
 * utilisé à l'écriture). Sans conséquence pratique pour ce chantier :
 * une colonne texte nullable stockant délibérément '' plutôt que NULL
 * est un cas très rare dans ce schéma.
 */
class TableExport extends StringValueBinder implements FromQuery, WithCustomValueBinder, WithHeadings, WithStrictNullComparison, WithTitle
{
    /** @var list<string> */
    private readonly array $columns;

    public function __construct(private readonly string $table)
    {
        $this->columns = Schema::getColumnListing($table);
    }

    public function query(): Builder
    {
        $query = DB::table($this->table)->select($this->columns);

        // Un ORDER BY déterministe est requis par le moteur de lecture sous-
        // jacent (cursor/chunk). "id" suffit pour les tables qui en ont une ;
        // les tables sans id (pivots) sont ordonnées sur l'ensemble de leurs
        // colonnes pour garantir un ordre stable malgré tout.
        if (in_array('id', $this->columns, true)) {
            return $query->orderBy('id');
        }

        foreach ($this->columns as $column) {
            $query->orderBy($column);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->columns;
    }

    public function title(): string
    {
        // Nom de feuille Excel limité à 31 caractères.
        return substr($this->table, 0, 31);
    }
}
