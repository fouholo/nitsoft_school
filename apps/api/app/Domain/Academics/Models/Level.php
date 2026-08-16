<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use App\Domain\Academics\Enums\Cycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'level_wording',
        'cycle',
        'requires_series',
    ];

    protected $casts = [
        'cycle' => Cycle::class,
        'requires_series' => 'boolean',
    ];

    /**
     * Échelle de la moyenne générale d'une composition : 10 pour CP1/CP2/CE1,
     * 20 pour CE2/CM1/CM2 et pour tous les niveaux secondaire.
     */
    public function compositionAverageScale(): float
    {
        return in_array($this->level, ['CP1', 'CP2', 'CE1'], true) ? 10.0 : 20.0;
    }
}
