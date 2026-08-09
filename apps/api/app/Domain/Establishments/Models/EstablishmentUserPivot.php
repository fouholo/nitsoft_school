<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $establishment_id
 * @property int $user_id
 * @property string $role
 * @property bool $is_active
 * @property bool|null $is_general_admin
 * @property bool|null $is_local_admin
 */
class EstablishmentUserPivot extends Pivot
{
    protected $table = 'establishment_user';

    protected $casts = [
        'is_active' => 'boolean',
        'is_general_admin' => 'boolean',
        'is_local_admin' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Establishment, $this>
     */
    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}
