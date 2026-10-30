<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Concerns\TenantScoped;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Résultat d'un élève à une composition : l'appréciation du maître est
 * enregistrée dès la saisie ; total, moyenne, mention et rang sont figés à
 * la génération des relevés.
 *
 * @property int $id
 * @property int $establishment_id
 * @property int $arabic_composition_id
 * @property int $enrollment_id
 * @property int $arabic_classroom_id
 * @property string|null $total
 * @property string|null $average
 * @property string|null $mention
 * @property int|null $rank
 * @property string|null $appreciation
 * @property Carbon|null $generated_at
 * @property-read ArabicComposition|null $arabicComposition
 * @property-read ArabicClassroom|null $arabicClassroom
 * @property-read Enrollment|null $enrollment
 */
class ArabicCompositionResult extends Model
{
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'arabic_composition_id',
        'enrollment_id',
        'arabic_classroom_id',
        'total',
        'average',
        'mention',
        'rank',
        'appreciation',
        'generated_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'average' => 'decimal:2',
        'rank' => 'integer',
        'generated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ArabicComposition, $this>
     */
    public function arabicComposition(): BelongsTo
    {
        return $this->belongsTo(ArabicComposition::class);
    }

    /**
     * @return BelongsTo<ArabicClassroom, $this>
     */
    public function arabicClassroom(): BelongsTo
    {
        return $this->belongsTo(ArabicClassroom::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
