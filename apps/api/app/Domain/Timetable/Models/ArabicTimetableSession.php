<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Models;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Séance hebdomadaire d'une classe arabe (classe × matière × enseignant ×
 * jour × créneau arabe), miroir de TimetableSession — voir
 * docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
 *
 * @property int $id
 * @property int $establishment_id
 * @property int $arabic_classroom_id
 * @property int $arabic_subject_id
 * @property int $user_id
 * @property DayOfWeek $day_of_week
 * @property int $arabic_timetable_slot_id
 * @property string|null $room
 */
class ArabicTimetableSession extends Model
{
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'school_year_id',
        'arabic_classroom_id',
        'arabic_subject_id',
        'user_id',
        'day_of_week',
        'arabic_timetable_slot_id',
        'room',
    ];

    protected $casts = [
        'day_of_week' => DayOfWeek::class,
    ];

    /**
     * @return BelongsTo<ArabicClassroom, $this>
     */
    public function arabicClassroom(): BelongsTo
    {
        return $this->belongsTo(ArabicClassroom::class);
    }

    /**
     * @return BelongsTo<ArabicSubject, $this>
     */
    public function arabicSubject(): BelongsTo
    {
        return $this->belongsTo(ArabicSubject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<ArabicTimetableSlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(ArabicTimetableSlot::class, 'arabic_timetable_slot_id');
    }

    /**
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
