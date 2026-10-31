<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'student_id',
        'discount_preset_id',
        'school_year_id',
        'type',
        'value',
        'registration_type',
        'registration_value',
        'reason',
        'created_by',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'registration_value' => 'decimal:2',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '242';
    }

    /**
     * Libellé d'une réduction : pourcentage, montant, ou tiret sans réduction.
     */
    public static function formatReduction(?string $type, mixed $value): string
    {
        return match ($type) {
            null => '—',
            'percentage' => number_format((float) $value, 2).' %',
            default => money((float) $value),
        };
    }

    public function hasTuitionDiscount(): bool
    {
        return $this->type !== null;
    }

    public function hasRegistrationDiscount(): bool
    {
        return $this->registration_type !== null;
    }

    /**
     * @return BelongsTo<DiscountPreset, $this>
     */
    public function discountPreset(): BelongsTo
    {
        return $this->belongsTo(DiscountPreset::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
