<?php

declare(strict_types=1);

namespace App\Domain\Sync\Concerns;

use App\Domain\Sync\Services\LocalUidGenerator;
use App\Domain\Sync\Services\UidServerAssigner;

/**
 * À utiliser sur tout modèle synchronisable par les clients offline
 * (Desktop/Mobile NativePHP) — colonnes uid_local/uid_serveur/device_id/
 * client_updated_at. Voir
 * docs/superpowers/specs/2026-08-10-uid-local-serveur-prefixe-design.md.
 */
trait Syncable
{
    protected static function bootSyncable(): void
    {
        static::creating(function ($model): void {
            if (! $model->uid_local) {
                $model->uid_local = app(LocalUidGenerator::class)->generate();
            }
            if (! $model->uid_serveur) {
                $model->uid_serveur = app(UidServerAssigner::class)->assign(static::uidPrefix());
            }
        });
    }

    abstract protected static function uidPrefix(): string;
}
