<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classroom extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'school_year_id',
        'name',
        'level_id',
        'serie_id',
        'numero',
        'capacity',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '212';
    }

    protected static function booted(): void
    {
        static::saving(function (Classroom $classroom): void {
            $classroom->name = trim(sprintf(
                '%s%s %s',
                $classroom->level->level_wording,
                $classroom->serie_id ? ' '.$classroom->serie->serie : '',
                $classroom->numero,
            ));
        });
    }

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
     * @return BelongsTo<Serie, $this>
     */
    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<TeacherAssignment, $this>
     */
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function isGradable(): bool
    {
        return $this->level->cycle !== Cycle::Prescolaire;
    }

    public function scopeGradable(Builder $query): Builder
    {
        return $query->whereHas('level', fn (Builder $q) => $q->where('cycle', '!=', Cycle::Prescolaire));
    }
}
