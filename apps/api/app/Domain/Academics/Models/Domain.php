<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;
    use Syncable;

    protected $fillable = [
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
        'name',
    ];

    protected $casts = [
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '219';
    }
}
