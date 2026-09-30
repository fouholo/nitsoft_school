<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

/**
 * Convertit un numéro tel que saisi dans l'application vers le format
 * international E.164 attendu par les fournisseurs SMS. Les numéros sont
 * stockés au format local ivoirien (0101010102) : depuis 2021, un numéro
 * ivoirien compte 10 chiffres et garde son 0 initial derrière +225.
 */
final class PhoneNumberNormalizer
{
    private const IVORIAN_PREFIX = '+225';

    public static function toE164(string $raw): ?string
    {
        $number = preg_replace('/[\s.\-()\/]/', '', $raw) ?? '';

        if (str_starts_with($number, '00')) {
            $number = '+'.substr($number, 2);
        }

        if (preg_match('/^0\d{9}$/', $number) === 1) {
            return self::IVORIAN_PREFIX.$number;
        }

        if (str_starts_with($number, self::IVORIAN_PREFIX)) {
            return preg_match('/^\+225\d{10}$/', $number) === 1 ? $number : null;
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $number) === 1 ? $number : null;
    }
}
