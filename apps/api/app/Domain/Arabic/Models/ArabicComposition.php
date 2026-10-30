<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Enums\SchoolMonth;
use App\Domain\Establishments\Concerns\TenantScoped;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Composition mensuelle du primaire arabe, commune à toute l'école : une par
 * mois et par année — voir
 * docs/superpowers/specs/2026-10-30-compositions-primaire-arabe-design.md.
 *
 * @property int $id
 * @property int $establishment_id
 * @property int $school_year_id
 * @property SchoolMonth $month
 * @property Carbon $graded_on
 * @property int $created_by
 * @property-read SchoolYear|null $schoolYear
 */
class ArabicComposition extends Model
{
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'school_year_id',
        'month',
        'graded_on',
        'created_by',
    ];

    protected $casts = [
        'month' => SchoolMonth::class,
        'graded_on' => 'date',
    ];

    /**
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * @return HasMany<ArabicCompositionGrade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(ArabicCompositionGrade::class);
    }

    /**
     * @return HasMany<ArabicCompositionResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(ArabicCompositionResult::class);
    }
}
