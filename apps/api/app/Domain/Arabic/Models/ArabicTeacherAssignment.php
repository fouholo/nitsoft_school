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
 * Affectation d'un enseignant à un groupe arabe (niveau × série arabe ×
 * matière arabe) — groupé par niveau/série arabe plutôt que par classe
 * française, contrairement à TeacherAssignment, puisque le niveau arabe est
 * porté par l'élève (Enrollment) et peut regrouper des élèves de classes
 * françaises différentes. Pas Syncable, comme TeacherAssignment (pas de
 * suivi offline sur les affectations).
 */
class ArabicTeacherAssignment extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $table = 'teacher_arabic_level_subject';

    protected $fillable = [
        'establishment_id',
        'user_id',
        'arabic_level_id',
        'arabic_serie_id',
        'arabic_subject_id',
        'school_year_id',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function arabicLevel(): BelongsTo
    {
        return $this->belongsTo(ArabicLevel::class);
    }

    public function arabicSerie(): BelongsTo
    {
        return $this->belongsTo(ArabicSerie::class);
    }

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
