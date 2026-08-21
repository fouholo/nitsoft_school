<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Une évaluation arabe (niveau × série arabe × matière × période) — miroir
 * de GradeSheet, sans la dualité de colonnes du français puisque
 * ArabicSubject/ArabicTerm sont déjà unifiés pour tous les cycles.
 */
class ArabicGradeSheet extends Model
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
        'arabic_term_id',
        'teacher_id',
        'title',
        'type',
        'max_score',
        'weight',
        'graded_on',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'max_score' => 'decimal:2',
        'weight' => 'decimal:2',
        'graded_on' => 'date',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '253';
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

    public function arabicTerm(): BelongsTo
    {
        return $this->belongsTo(ArabicTerm::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return HasMany<ArabicGrade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(ArabicGrade::class);
    }
}
