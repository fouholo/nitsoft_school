<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Créneau de la grille arabe d'un établissement, distincte de la grille
 * française (TimetableSlot) — voir
 * docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
 *
 * @property int $id
 * @property int $establishment_id
 * @property string $label
 * @property string $start_time
 * @property string $end_time
 * @property int $sequence
 * @property bool $is_break
 */
class ArabicTimetableSlot extends Model
{
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'label',
        'start_time',
        'end_time',
        'sequence',
        'is_break',
    ];

    protected $casts = [
        'is_break' => 'boolean',
    ];

    /**
     * @return HasMany<ArabicTimetableSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ArabicTimetableSession::class, 'arabic_timetable_slot_id');
    }
}
