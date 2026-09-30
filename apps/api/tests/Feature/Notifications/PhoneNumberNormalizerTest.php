<?php

declare(strict_types=1);

use App\Domain\Notifications\Support\PhoneNumberNormalizer;

test('convertit un numéro vers le format international', function (string $raw, ?string $expected) {
    expect(PhoneNumberNormalizer::toE164($raw))->toBe($expected);
})->with([
    'local ivoirien' => ['0101010102', '+2250101010102'],
    'local avec espaces' => ['07 00 00 00 00', '+2250700000000'],
    'local avec points et tirets' => ['05.06-07.08.09', '+2250506070809'],
    'déjà international' => ['+2250700000000', '+2250700000000'],
    'international avec espaces' => ['+225 07 00 00 00 00', '+2250700000000'],
    'préfixe 00' => ['002250700000000', '+2250700000000'],
    'numéro étranger' => ['+33612345678', '+33612345678'],
    'ancien format ivoirien à 8 chiffres' => ['07000000', null],
    '+225 trop court' => ['+22507000000', null],
    'texte' => ['pas un numéro', null],
    'vide' => ['', null],
]);
