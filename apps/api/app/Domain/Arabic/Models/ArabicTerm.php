<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Période arabe, propre à chaque établissement × année scolaire — miroir
 * simplifié de Term : starts_on/ends_on restent nuls pour un ArabicLevel de
 * cycle Préscolaire/Primaire-équivalent, sequence faisant alors office de
 * numéro de composition. Voir
 * docs/superpowers/specs/2026-08-21-arabe-affectations-notes-design.md.
 */
class ArabicTerm extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'school_year_id',
        'label',
        'starts_on',
        'ends_on',
        'sequence',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '252';
    }

    /**
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
