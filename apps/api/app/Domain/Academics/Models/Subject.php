<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;

    protected $fillable = [
        'name',
        'abbreviation',
        'is_prescolaire_primaire',
        'is_secondaire',
        'domain_id',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'is_prescolaire_primaire' => 'boolean',
        'is_secondaire' => 'boolean',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '215';
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
