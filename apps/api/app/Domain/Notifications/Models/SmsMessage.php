<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Establishments\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SmsMessage extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'guardian_id',
        'template_code',
        'phone',
        'body_rendered',
        'status',
        'provider',
        'provider_message_id',
        'error_message',
        'sent_at',
        'related_type',
        'related_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
