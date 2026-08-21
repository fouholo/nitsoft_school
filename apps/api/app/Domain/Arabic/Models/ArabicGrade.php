<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Note arabe, rattachée à l'Enrollment plutôt qu'au Student (contrairement à
 * Grade côté français) — cohérent avec le fait qu'arabic_level_id/
 * arabic_serie_id vivent déjà sur Enrollment, et fige le niveau/série arabe
 * de l'élève au moment de la note.
 */
class ArabicGrade extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'arabic_grade_sheet_id',
        'enrollment_id',
        'score',
        'comment',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '254';
    }

    /**
     * @return BelongsTo<ArabicGradeSheet, $this>
     */
    public function arabicGradeSheet(): BelongsTo
    {
        return $this->belongsTo(ArabicGradeSheet::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
