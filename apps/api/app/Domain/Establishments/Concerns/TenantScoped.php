<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Concerns;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Scopes\EstablishmentScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * À utiliser sur tout modèle possédant une colonne establishment_id.
 * Ajoute le Global Scope d'isolation tenant et l'auto-assignation de
 * establishment_id à la création depuis le tenant courant.
 */
trait TenantScoped
{
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope(new EstablishmentScope);

        static::creating(function ($model): void {
            if (! $model->establishment_id && app()->bound('currentEstablishmentId')) {
                $model->establishment_id = app('currentEstablishmentId');
            }
        });
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope(EstablishmentScope::class);
    }
}
