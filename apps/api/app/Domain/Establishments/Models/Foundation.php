<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use App\Domain\Sync\Concerns\Syncable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un fondateur peut posséder plusieurs établissements (groupe scolaire).
 * L'isolation tenant reste basée sur establishment_id (voir TenantScoped) :
 * la Foundation ne change pas la frontière d'accès aux données, elle donne
 * juste à ses membres (rôle "founder") un accès admin-équivalent sur chacun
 * des établissements du groupe, sans ligne establishment_user par école.
 */
class Foundation extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'uid',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'client_updated_at' => 'datetime',
    ];

    /**
     * @return HasMany<Establishment, $this>
     */
    public function establishments(): HasMany
    {
        return $this->hasMany(Establishment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'foundation_user')
            ->using(FoundationUserPivot::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }
}
