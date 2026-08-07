<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Models;

use App\Domain\Sync\Concerns\Syncable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'uid',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'client_updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Student, $this, GuardianStudentPivot, 'pivot'>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->using(GuardianStudentPivot::class)
            ->withPivot(['id', 'establishment_id', 'is_primary_contact', 'status', 'relationship'])
            ->withTimestamps();
    }
}
