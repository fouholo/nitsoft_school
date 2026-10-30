<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Subject;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use App\Domain\Timetable\Models\ArabicTimetableSession;
use App\Domain\Timetable\Models\TimetableSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceSession extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'classroom_id',
        'arabic_classroom_id',
        'subject_id',
        'arabic_subject_id',
        'timetable_session_id',
        'arabic_timetable_session_id',
        'teacher_id',
        'session_date',
        'started_at',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'session_date' => 'date',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '233';
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<TimetableSession, $this>
     */
    public function timetableSession(): BelongsTo
    {
        return $this->belongsTo(TimetableSession::class);
    }

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
     * @return BelongsTo<ArabicTimetableSession, $this>
     */
    public function arabicTimetableSession(): BelongsTo
    {
        return $this->belongsTo(ArabicTimetableSession::class);
    }

    /**
     * Un appel porte soit sur une classe française, soit sur une classe
     * arabe — voir docs/superpowers/specs/2026-10-30-appel-classes-arabes-design.md.
     */
    public function isArabic(): bool
    {
        return $this->arabic_classroom_id !== null;
    }

    public function classroomName(): ?string
    {
        return $this->isArabic() ? $this->arabicClassroom?->name : $this->classroom?->name;
    }

    public function subjectName(): ?string
    {
        return $this->isArabic() ? $this->arabicSubject?->name : $this->subject?->name;
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
