<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Sync\Concerns\Syncable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Establishment extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;

    protected $fillable = [
        'foundation_id',
        'name',
        'slug',
        'type',
        'address',
        'phone',
        'is_active',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'type' => EstablishmentType::class,
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '211';
    }

    /**
     * @return BelongsTo<Foundation, $this>
     */
    public function foundation(): BelongsTo
    {
        return $this->belongsTo(Foundation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'establishment_user')
            ->using(EstablishmentUserPivot::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }
}
