<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LevelFee extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'school_year_id',
        'level_id',
        'registration_amount',
        'uid',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'registration_amount' => 'decimal:2',
        'client_updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (LevelFee $levelFee): void {
            $levelFee->installmentAmounts()->delete();
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
     * @return BelongsTo<Level, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * @return HasMany<LevelFeeInstallment, $this>
     */
    public function installmentAmounts(): HasMany
    {
        return $this->hasMany(LevelFeeInstallment::class);
    }
}
