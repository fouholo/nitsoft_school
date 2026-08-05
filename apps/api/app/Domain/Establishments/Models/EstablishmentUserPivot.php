<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $role
 * @property bool $is_active
 */
class EstablishmentUserPivot extends Pivot
{
    protected $table = 'establishment_user';

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
