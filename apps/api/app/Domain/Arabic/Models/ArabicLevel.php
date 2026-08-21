<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use App\Domain\Academics\Enums\Cycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Nomenclature de niveaux propre à la filière arabe, indépendante de Level
 * (français) — catalogue global géré par l'admin SaaS. Voir
 * docs/superpowers/specs/2026-08-21-arabe-fondations-design.md.
 */
class ArabicLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'wording',
        'cycle',
        'requires_series',
    ];

    protected $casts = [
        'cycle' => Cycle::class,
        'requires_series' => 'boolean',
    ];
}
