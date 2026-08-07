<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'first_name',
        'last_name',
        'birth_date',
        'gender',
        'student_number',
        'photo_path',
        'is_active',
        'father_name',
        'father_phone',
        'mother_name',
        'mother_phone',
        'tutor_name',
        'tutor_phone',
        'uid',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'client_updated_at' => 'datetime',
    ];

    /**
     * @return BelongsToMany<Guardian, $this, GuardianStudentPivot, 'pivot'>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
            ->using(GuardianStudentPivot::class)
            ->withPivot(['id', 'establishment_id', 'is_primary_contact', 'status', 'relationship'])
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
