<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArabicLevelFeeInstallment extends Model
{
    protected $fillable = [
        'arabic_level_fee_id',
        'installment_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<ArabicLevelFee, $this>
     */
    public function arabicLevelFee(): BelongsTo
    {
        return $this->belongsTo(ArabicLevelFee::class);
    }

    /**
     * @return BelongsTo<Installment, $this>
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }
}
