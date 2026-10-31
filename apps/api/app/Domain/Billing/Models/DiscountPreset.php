<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Establishments\Concerns\TenantScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réduction type du catalogue de l'école : ses valeurs sont copiées dans la
 * réduction (Discount) de l'élève au moment de l'application — voir
 * docs/superpowers/specs/2026-10-31-catalogue-reductions-design.md.
 */
class DiscountPreset extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'establishment_id',
        'name',
        'type',
        'value',
        'registration_type',
        'registration_value',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'registration_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * @param  Builder<DiscountPreset>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('name');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
