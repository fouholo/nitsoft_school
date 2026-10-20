<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Classe de la filière arabe (niveau arabe + série arabe éventuelle +
 * numéro), miroir de Classroom — unité de travail de la filière arabe
 * (affectations, notes, bulletins). Un élève d'école franco-arabe y est
 * inscrit en classe principale ou secondaire — voir
 * docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 *
 * @property int $id
 * @property int $establishment_id
 * @property int $school_year_id
 * @property int $arabic_level_id
 * @property int|null $arabic_serie_id
 * @property string|null $numero
 * @property string $name
 * @property int|null $capacity
 * @property-read ArabicLevel $arabicLevel
 * @property-read ArabicSerie|null $arabicSerie
 * @property-read SchoolYear|null $schoolYear
 */
class ArabicClassroom extends Model
{
    use HasFactory;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'school_year_id',
        'arabic_level_id',
        'arabic_serie_id',
        'numero',
        'capacity',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '255';
    }

    protected static function booted(): void
    {
        // Libellé français du niveau (à défaut le libellé arabe) : le nom
        // apparaît sur les reçus et écrans financiers, rédigés en français.
        static::saving(function (ArabicClassroom $arabicClassroom): void {
            $level = $arabicClassroom->arabicLevel()->firstOrFail();
            $serie = $arabicClassroom->arabic_serie_id ? $arabicClassroom->arabicSerie()->first() : null;

            $arabicClassroom->name = trim(sprintf(
                '%s%s %s',
                $level->wording_fr ?: $level->wording,
                $serie ? ' '.$serie->serie : '',
                (string) $arabicClassroom->numero,
            ));
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
     * @return BelongsTo<ArabicSerie, $this>
     */
    public function arabicSerie(): BelongsTo
    {
        return $this->belongsTo(ArabicSerie::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<ArabicTeacherAssignment, $this>
     */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(ArabicTeacherAssignment::class);
    }
}
