<?php

declare(strict_types=1);

namespace App\Domain\Backup\Support;

/**
 * Marqueur non-Eloquent, cible des authorize()/Gate::before pour les
 * abilities export/wipe/import — n'a aucune donnée ni persistance propre.
 * Voir App\Policies\BackupOperationPolicy (nom dérivé par auto-discovery).
 */
final class BackupOperation
{
    //
}
