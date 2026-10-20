<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Models;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Affectation d'un enseignant à une classe arabe pour une matière arabe —
 * miroir de TeacherAssignment (classe française). Regroupée par classe
 * arabe depuis docs/superpowers/specs/2026-10-20-classes-arabes-design.md
 * (auparavant par niveau/série arabe). Pas Syncable, comme
 * TeacherAssignment (pas de suivi offline sur les affectations).
 */
class ArabicTeacherAssignment extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $table = 'teacher_arabic_classroom_subject';

    protected $fillable = [
        'establishment_id',
        'user_id',
        'arabic_classroom_id',
        'arabic_subject_id',
        'school_year_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
