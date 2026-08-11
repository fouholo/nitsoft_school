<?php

declare(strict_types=1);

namespace App\Domain\Sync\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Affecte un uid_serveur de 12 caractères (3 de préfixe par type d'entité +
 * 9 de séquence), incrémenté par un compteur atomique par préfixe. Remplace
 * l'ancien UidAssigner (compteur global sans préfixe). Voir
 * docs/superpowers/specs/2026-08-10-uid-local-serveur-prefixe-design.md.
 * `lockForUpdate()` dans une transaction est l'idiome portable (MySQL et
 * SQLite, ce dernier utilisé par la suite de tests) pour un compteur
 * atomique par clé — l'idiome `LAST_INSERT_ID(col + 1)` envisagé dans la
 * spec est spécifique à MySQL et échoue sous SQLite (à ne pas réutiliser).
 */
class UidServerAssigner
{
    public function assign(string $prefix): string
    {
        return DB::transaction(function () use ($prefix): string {
            $counter = DB::table('uid_server_counters')
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                throw new RuntimeException("Préfixe uid inconnu : {$prefix}");
            }

            $value = ((int) $counter->next_value) + 1;

            DB::table('uid_server_counters')
                ->where('prefix', $prefix)
                ->update(['next_value' => $value]);

            return $prefix.str_pad((string) $value, 9, '0', STR_PAD_LEFT);
        });
    }
}
