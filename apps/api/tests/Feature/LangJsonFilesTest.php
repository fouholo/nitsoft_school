<?php

declare(strict_types=1);

test('les fichiers de traduction JSON sont valides, sans clé vide ni doublon', function (string $path) {
    $raw = file_get_contents($path);
    expect($raw)->not->toBeFalse();

    $decoded = json_decode((string) $raw, true, flags: JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray()->not->toBeEmpty();

    foreach ($decoded as $key => $value) {
        expect(trim((string) $key))->not->toBe('', "Clé vide trouvée dans {$path}");
        expect(trim((string) $value))->not->toBe('', "Traduction vide pour la clé \"{$key}\" dans {$path}");
    }

    $keys = array_keys($decoded);
    expect($keys)->toBe(array_unique($keys), "Clé dupliquée trouvée dans {$path}");
})->with([
    dirname(__DIR__, 2).'/lang/en.json',
    dirname(__DIR__, 2).'/lang/ar.json',
]);
