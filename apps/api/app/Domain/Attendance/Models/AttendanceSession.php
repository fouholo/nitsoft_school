<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Subject;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
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
        'subject_id',
        'teacher_id',
        'session_date',
        'started_at',
        'uuid',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'session_date' => 'date',
        'client_updated_at' => 'datetime',
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
