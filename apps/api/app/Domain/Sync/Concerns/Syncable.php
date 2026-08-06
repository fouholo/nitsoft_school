<?php

declare(strict_types=1);

namespace App\Domain\Sync\Concerns;

use App\Domain\Sync\Services\UidAssigner;

/**
 * À utiliser sur tout modèle synchronisable par les clients offline
 * (Desktop/Mobile NativePHP) — colonnes uid/device_id/client_updated_at.
 * Voir docs/superpowers/specs/2026-08-06-uid-synchronisation-design.md.
 */
trait Syncable
{
    protected static function bootSyncable(): void
    {
        static::creating(function ($model): void {
            if (! $model->uid) {
                $model->uid = app(UidAssigner::class)->assign();
            }
        });
    }
}
