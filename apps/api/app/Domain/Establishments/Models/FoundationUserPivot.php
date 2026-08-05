<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $role
 * @property bool $is_active
 */
class FoundationUserPivot extends Pivot
{
    protected $table = 'foundation_user';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
