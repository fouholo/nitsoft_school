<?php

declare(strict_types=1);

use App\Domain\Sync\Services\LocalUidGenerator;

test('generate retourne une chaîne de 20 caractères en alphabet Crockford', function () {
    $uid = (new LocalUidGenerator)->generate();

    expect($uid)->toHaveLength(20)
        ->and($uid)->toMatch('/^[0-9A-HJKMNP-TV-Z]{20}$/');
});

test('generate produit des valeurs différentes à chaque appel', function () {
    $generator = new LocalUidGenerator;

    expect($generator->generate())->not->toBe($generator->generate());
});
