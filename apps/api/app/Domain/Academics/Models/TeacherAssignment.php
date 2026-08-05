<?php

declare(strict_types=1);

namespace App\Domain\Academics\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAssignment extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $table = 'teacher_classroom_subject';

    protected $fillable = [
        'establishment_id',
        'user_id',
        'classroom_id',
        'subject_id',
        'school_year_id',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
