<?php

declare(strict_types=1);

namespace App\Domain\Sync\Services;

use Illuminate\Support\Facades\DB;

/**
 * Affecte un uid numérique de 12 caractères, incrémenté par un compteur
 * global partagé par toutes les tables et tous les établissements — voir
 * docs/superpowers/specs/2026-08-06-uid-synchronisation-design.md.
 * insertGetId() sur uid_counters est atomique sous concurrence MySQL sans
 * verrou explicite (contrairement à PaymentService::nextReceiptNumber(),
 * qui ne doit pas être pris comme modèle ici).
 */
class UidAssigner
{
    public function assign(): string
    {
        $id = DB::table('uid_counters')->insertGetId([]);

        return str_pad((string) $id, 12, '0', STR_PAD_LEFT);
    }
}
