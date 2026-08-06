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
        'level',
        'cycle',
        'capacity',
        'uid',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'cycle' => Cycle::class,
        'client_updated_at' => 'datetime',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function isGradable(): bool
    {
        return $this->cycle !== Cycle::Prescolaire;
    }

    public function scopeGradable(Builder $query): Builder
    {
        return $query->where('cycle', '!=', Cycle::Prescolaire);
    }
}
