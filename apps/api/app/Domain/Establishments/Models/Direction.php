<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Models;

use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;

class Direction extends Model
{
    use Syncable;

    protected $fillable = [
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
        'code',
        'direction_name',
        'address',
        'phone_number',
        'email',
        'location',
        'logo_path',
    ];

    protected $casts = [
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '218';
    }
}
