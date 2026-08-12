<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Concerns\HasOwnerScope;
use App\Domain\Billing\Contracts\HasOwnerColumn;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Concerns\TenantScoped;
use App\Domain\Sync\Concerns\Syncable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $uid_local
 * @property string|null $uid_serveur
 */
class Payment extends Model implements HasOwnerColumn
{
    use HasFactory;
    use HasOwnerScope;
    use SoftDeletes;
    use Syncable;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'invoice_id',
        'student_id',
        'amount',
        'method',
        'paid_at',
        'received_by',
        'reference',
        'uid_local',
        'uid_serveur',
        'device_id',
        'client_updated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '241';
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function ownerColumn(): string
    {
        return 'received_by';
    }

    /**
     * Numéro de reçu affiché : uid_serveur une fois synchronisé, sinon
     * uid_local (généré hors-ligne, en attente de synchronisation).
     */
    public function receiptNumber(): string
    {
        return $this->uid_serveur ?? $this->uid_local;
    }
}
