<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Matière retenue par une école pour un niveau arabe du primaire, avec son
 * ordre d'affichage sur le relevé — voir
 * docs/superpowers/specs/2026-10-30-compositions-primaire-arabe-design.md.
 *
 * @property int $id
 * @property int $establishment_id
 * @property int $arabic_level_id
 * @property int $arabic_subject_id
 * @property int $sequence
 * @property-read ArabicSubject|null $arabicSubject
 */
class ArabicLevelSubject extends Model
{
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'arabic_level_id',
        'arabic_subject_id',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    /**
     * @return BelongsTo<ArabicSubject, $this>
     */
    public function arabicSubject(): BelongsTo
    {
        return $this->belongsTo(ArabicSubject::class);
    }
}
