<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Note d'un élève à une matière d'une composition, sur 10.
 *
 * @property int $id
 * @property int $establishment_id
 * @property int $arabic_composition_id
 * @property int $enrollment_id
 * @property int $arabic_subject_id
 * @property string|null $score
 * @property bool $is_absent
 */
class ArabicCompositionGrade extends Model
{
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'arabic_composition_id',
        'enrollment_id',
        'arabic_subject_id',
        'score',
        'is_absent',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'is_absent' => 'boolean',
    ];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<ArabicSubject, $this>
     */
    public function arabicSubject(): BelongsTo
    {
        return $this->belongsTo(ArabicSubject::class);
    }
}
