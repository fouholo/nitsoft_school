<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bulletin arabe, séparé du ReportCard français (deux bulletins distincts
 * par élève). Pas de pdf_path : le PDF est toujours rendu à la volée, jamais
 * stocké (voir ArabicReportCardPdfController) — miroir de ReportCard, sans
 * son champ pdf_path resté inutilisé. Pas Syncable (comme ReportCard).
 */
class ArabicReportCard extends Model
{
    use HasFactory;
    use SoftDeletes;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'enrollment_id',
        'arabic_term_id',
        'average',
        'rank',
        'appreciation',
        'generated_at',
    ];

    protected $casts = [
        'average' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<ArabicTerm, $this>
     */
    public function arabicTerm(): BelongsTo
    {
        return $this->belongsTo(ArabicTerm::class);
    }
}
