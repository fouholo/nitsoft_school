<?php

declare(strict_types=1);

namespace App\Domain\Sync\Services;

/**
 * Génère un uid_local de 20 caractères (10 horodatage + 10 aléatoire, Base32
 * Crockford) sans dépendance base de données — doit pouvoir fonctionner
 * hors-ligne une fois porté sur Desktop/Mobile. Voir
 * docs/superpowers/specs/2026-08-10-uid-local-serveur-prefixe-design.md.
 */
class LocalUidGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function generate(): string
    {
        return $this->encode((int) (microtime(true) * 1000), 10)
            .$this->encode(random_int(0, PHP_INT_MAX), 10);
    }

    private function encode(int $value, int $length): string
    {
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out = self::ALPHABET[$value % 32].$out;
            $value = intdiv($value, 32);
        }

        return $out;
    }
}
