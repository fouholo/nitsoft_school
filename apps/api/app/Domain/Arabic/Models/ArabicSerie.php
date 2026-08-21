<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Filière interne de la piste arabe (indépendante des séries A/C/D du
 * Secondaire français) — catalogue global géré par l'admin SaaS. Applicable
 * uniquement aux inscriptions dont l'ArabicLevel a requires_series = true.
 */
class ArabicSerie extends Model
{
    use HasFactory;

    protected $fillable = [
        'serie',
        'serie_wording',
    ];
}
