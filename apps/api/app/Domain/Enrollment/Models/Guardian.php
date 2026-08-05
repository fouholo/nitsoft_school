<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
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
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'relationship',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->withPivot('is_primary_contact')
            ->withTimestamps();
    }
}
