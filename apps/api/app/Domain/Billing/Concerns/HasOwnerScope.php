<?php

declare(strict_types=1);

namespace App\Domain\Billing\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * À utiliser sur les modèles implémentant HasOwnerColumn (Payment,
 * Expense) — restreint une requête aux enregistrements saisis par un
 * utilisateur donné (portée "propres saisies" de l'éducateur, voir
 * RolePermissions::MATRIX['finance.scope_own_only']).
 */
trait HasOwnerScope
{
    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where($this->ownerColumn(), $user->id);
    }
}
