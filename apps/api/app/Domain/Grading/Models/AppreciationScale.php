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
     * $average est une moyenne sur 20 (convention utilisée par
     * ReportCardService/EnterStudent) — convertie en pourcentage pour
     * trouver la tranche la plus haute atteinte.
     */
    public static function forAverage(float $average): ?self
    {
        $percentage = ($average / 20) * 100;

        return static::query()
            ->where('percentage', '<=', $percentage)
            ->orderByDesc('percentage')
            ->first();
    }
}
