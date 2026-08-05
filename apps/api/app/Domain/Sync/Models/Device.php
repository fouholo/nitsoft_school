<?php

declare(strict_types=1);

namespace App\Domain\Sync\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\PersonalAccessToken;

class Device extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'user_id',
        'platform',
        'device_uuid',
        'personal_access_token_id',
        'last_seen_at',
        'is_revoked',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_revoked' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function personalAccessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class);
    }

    public function syncCursors(): HasMany
    {
        return $this->hasMany(SyncCursor::class);
    }
}
