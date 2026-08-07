<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Models;

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Enums\GuardianRelationship;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $establishment_id
 * @property int $guardian_id
 * @property int $student_id
 * @property bool $is_primary_contact
 * @property GuardianLinkStatus $status
 * @property ?GuardianRelationship $relationship
 */
class GuardianStudentPivot extends Pivot
{
    protected $table = 'guardian_student';

    protected $casts = [
        'is_primary_contact' => 'boolean',
        'status' => GuardianLinkStatus::class,
        'relationship' => GuardianRelationship::class,
    ];

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
