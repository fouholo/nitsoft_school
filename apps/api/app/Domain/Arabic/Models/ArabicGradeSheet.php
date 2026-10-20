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
 * Une évaluation arabe (classe arabe × matière × période) — miroir de
 * GradeSheet, sans la dualité de colonnes du français puisque
 * ArabicSubject/ArabicTerm sont déjà unifiés pour tous les cycles. Regroupée
 * par classe arabe depuis
 * docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */
class ArabicGradeSheet extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'arabic_classroom_id',
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

    /**
     * @return BelongsTo<ArabicClassroom, $this>
     */
    public function arabicClassroom(): BelongsTo
    {
        return $this->belongsTo(ArabicClassroom::class);
    }

    /**
     * @return BelongsTo<ArabicSubject, $this>
     */
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
