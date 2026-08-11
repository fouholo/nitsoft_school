<?php

declare(strict_types=1);

use App\Domain\Sync\Services\UidServerAssigner;

test('assign retourne le préfixe suivi de 9 chiffres zéro-paddés', function () {
    $uid = (new UidServerAssigner)->assign('211');

    expect($uid)->toMatch('/^211\d{9}$/');
});

test('assign incrémente et reste unique sur des appels successifs pour un même préfixe', function () {
    $assigner = new UidServerAssigner;

    $uids = collect(range(1, 200))->map(fn () => $assigner->assign('211'));

    expect($uids->unique())->toHaveCount(200);
});

test('assign a des séquences indépendantes par préfixe', function () {
    $assigner = new UidServerAssigner;

    $first = $assigner->assign('210');
    $second = $assigner->assign('211');

    expect($first)->toStartWith('210')
        ->and($second)->toStartWith('211');
});

test('assign lève une exception sur un préfixe inconnu', function () {
    (new UidServerAssigner)->assign('999');
})->throws(RuntimeException::class);
