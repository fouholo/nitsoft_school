<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'name',
        'uid',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'client_updated_at' => 'datetime',
    ];
}
