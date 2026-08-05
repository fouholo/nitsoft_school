<?php

declare(strict_types=1);

namespace App\Domain\Sync\Concerns;

use Illuminate\Support\Str;

/**
 * À utiliser sur tout modèle synchronisable par les clients offline
 * (Desktop/Mobile NativePHP) — colonnes uuid/device_id/client_updated_at.
 * Voir plan d'architecture, section 5.4 (stratégie Last-Write-Wins).
 */
trait Syncable
{
    protected static function bootSyncable(): void
    {
        static::creating(function ($model): void {
            if (! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
