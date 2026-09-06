<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Models;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pas de SoftDeletes : voir TimetableSlot, même raisonnement (table
 * d'affectation comme TeacherAssignment, pas un enregistrement
 * historique).
 */
class TimetableSession extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'school_year_id',
        'classroom_id',
        'subject_id',
        'user_id',
        'day_of_week',
        'timetable_slot_id',
        'room',
    ];

    protected $casts = [
        'day_of_week' => DayOfWeek::class,
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<TimetableSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
