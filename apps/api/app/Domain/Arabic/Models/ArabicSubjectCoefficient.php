<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Coefficient d'une matière arabe pour un établissement × niveau arabe ×
 * série arabe (nullable). Miroir de SubjectCoefficient, propre à chaque
 * établissement — contrairement aux catalogues ArabicLevel/ArabicSerie/
 * ArabicSubject qui sont globaux.
 */
class ArabicSubjectCoefficient extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'arabic_level_id',
        'arabic_serie_id',
        'arabic_subject_id',
        'coefficient',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'coefficient' => 'decimal:2',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '251';
    }

    public function arabicLevel(): BelongsTo
    {
        return $this->belongsTo(ArabicLevel::class);
    }

    public function arabicSerie(): BelongsTo
    {
        return $this->belongsTo(ArabicSerie::class);
    }

    public function arabicSubject(): BelongsTo
    {
        return $this->belongsTo(ArabicSubject::class);
    }
}
