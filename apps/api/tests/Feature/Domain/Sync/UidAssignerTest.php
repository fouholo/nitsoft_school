<?php

declare(strict_types=1);

use App\Domain\Sync\Services\UidAssigner;

test('assign retourne une chaîne de 12 chiffres zéro-paddée', function () {
    $uid = (new UidAssigner)->assign();

    expect($uid)->toMatch('/^\d{12}$/');
});

test('assign incrémente et reste unique sur des appels successifs', function () {
    $assigner = new UidAssigner;

    $uids = collect(range(1, 200))->map(fn () => $assigner->assign());

    expect($uids->unique())->toHaveCount(200);
});
