<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrimarySubject extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;

    protected $fillable = [
        'name',
        'abbreviation',
        'coefficient_cp1',
        'coefficient_cp2',
        'coefficient_ce1',
        'coefficient_ce2',
        'coefficient_cm1',
        'coefficient_cm2',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'coefficient_cp1' => 'decimal:2',
        'coefficient_cp2' => 'decimal:2',
        'coefficient_ce1' => 'decimal:2',
        'coefficient_ce2' => 'decimal:2',
        'coefficient_cm1' => 'decimal:2',
        'coefficient_cm2' => 'decimal:2',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '224';
    }

    public static function coefficientColumn(Level $level): string
    {
        return match ($level->level) {
            'CP1' => 'coefficient_cp1',
            'CP2' => 'coefficient_cp2',
            'CE1' => 'coefficient_ce1',
            'CE2' => 'coefficient_ce2',
            'CM1' => 'coefficient_cm1',
            'CM2' => 'coefficient_cm2',
            default => throw new \InvalidArgumentException("Niveau primaire inconnu : {$level->level}"),
        };
    }

    public function coefficientFor(Level $level): ?float
    {
        $value = $this->{self::coefficientColumn($level)};

        return $value !== null ? (float) $value : null;
    }
}
