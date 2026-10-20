<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Frais d'un niveau arabe pour une année (inscription + tranches), miroir
 * de LevelFee sans montant « affecté » — appliqués à l'élève dont la classe
 * principale est arabe. Voir
 * docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */
class ArabicLevelFee extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'school_year_id',
        'arabic_level_id',
        'registration_amount',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'registration_amount' => 'decimal:2',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '256';
    }

    protected static function booted(): void
    {
        static::deleting(function (ArabicLevelFee $arabicLevelFee): void {
            $arabicLevelFee->installmentAmounts()->delete();
        });
    }

    /**
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * @return BelongsTo<ArabicLevel, $this>
     */
    public function arabicLevel(): BelongsTo
    {
        return $this->belongsTo(ArabicLevel::class);
    }

    /**
     * @return HasMany<ArabicLevelFeeInstallment, $this>
     */
    public function installmentAmounts(): HasMany
    {
        return $this->hasMany(ArabicLevelFeeInstallment::class);
    }
}
