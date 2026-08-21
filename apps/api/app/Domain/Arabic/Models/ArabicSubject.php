<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalogue de matières arabes, unique pour tous les cycles — contrairement
 * au français (Subject/PrimarySubject séparés), la nomenclature ArabicLevel
 * étant libre plutôt qu'un ensemble fixe connu à l'avance, un seul modèle
 * suffit : les coefficients passent toujours par ArabicSubjectCoefficient
 * (table de jointure), jamais par des colonnes par niveau en dur. Catalogue
 * global géré par l'admin SaaS.
 */
class ArabicSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'abbreviation',
    ];
}
