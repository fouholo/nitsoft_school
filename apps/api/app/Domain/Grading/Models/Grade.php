<?php

declare(strict_types=1);

namespace App\Domain\Grading\Models;

use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grade extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'grade_sheet_id',
        'student_id',
        'score',
        'comment',
        'uid',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'client_updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<GradeSheet, $this>
     */
    public function gradeSheet(): BelongsTo
    {
        return $this->belongsTo(GradeSheet::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
