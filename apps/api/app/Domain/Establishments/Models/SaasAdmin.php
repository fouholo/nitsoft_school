<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use App\Domain\Establishments\Enums\SaasAdminType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasAdmin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'is_active',
        'is_main',
    ];

    protected $casts = [
        'type' => SaasAdminType::class,
        'is_active' => 'boolean',
        'is_main' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
