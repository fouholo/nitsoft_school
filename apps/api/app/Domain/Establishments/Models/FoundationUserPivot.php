<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $foundation_id
 * @property int $user_id
 * @property string $role
 * @property bool $is_active
 * @property bool|null $is_general_admin
 */
class FoundationUserPivot extends Pivot
{
    protected $table = 'foundation_user';

    protected $casts = [
        'is_active' => 'boolean',
        'is_general_admin' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
