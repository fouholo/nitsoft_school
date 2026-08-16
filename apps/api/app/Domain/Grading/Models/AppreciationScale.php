<?php

declare(strict_types=1);

namespace App\Domain\Grading\Models;

use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppreciationScale extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;

    protected $fillable = [
        'percentage',
        'appreciation',
        'tableau_honneur',
        'tableau_excellence',
        'felicitation',
        'encouragement',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'tableau_honneur' => 'boolean',
        'tableau_excellence' => 'boolean',
        'felicitation' => 'boolean',
        'encouragement' => 'boolean',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '235';
    }

    /**
     * $average est une moyenne sur $scale (20 par défaut ; 10 pour une
     * composition primaire CP1/CP2/CE1 — voir Level::compositionAverageScale())
     * — convertie en pourcentage pour trouver la tranche la plus haute atteinte.
     */
    public static function forAverage(float $average, float $scale = 20.0): ?self
    {
        $percentage = ($average / $scale) * 100;

        return static::query()
            ->where('percentage', '<=', $percentage)
            ->orderByDesc('percentage')
            ->first();
    }
}
