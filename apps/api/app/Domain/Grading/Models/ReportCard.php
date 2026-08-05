<?php

declare(strict_types=1);

namespace App\Domain\Grading\Models;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Term;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportCard extends Model
{
    use HasFactory;
    use SoftDeletes;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'student_id',
        'term_id',
        'classroom_id',
        'average',
        'rank',
        'generated_at',
        'pdf_path',
    ];

    protected $casts = [
        'average' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
