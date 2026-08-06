<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    use HasFactory;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'code',
        'body',
        'is_active',
        'uid',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'client_updated_at' => 'datetime',
    ];
}
