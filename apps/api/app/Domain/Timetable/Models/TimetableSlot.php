<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimetableSlot extends Model
{
    use HasFactory;
    use SoftDeletes;
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
     * @return HasMany<TimetableSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TimetableSession::class, 'timetable_slot_id');
    }
}
