<?php

declare(strict_types=1);

use App\Domain\Grading\Models\AppreciationScale;

beforeEach(function () {
    AppreciationScale::factory()->create(['percentage' => 90, 'appreciation' => 'Excellent']);
    AppreciationScale::factory()->create(['percentage' => 80, 'appreciation' => 'Très bien']);
    AppreciationScale::factory()->create(['percentage' => 70, 'appreciation' => 'Bien']);
    AppreciationScale::factory()->create(['percentage' => 0, 'appreciation' => 'Insuffisant']);
});

test('forAverage retourne la tranche la plus haute atteinte', function () {
    expect(AppreciationScale::forAverage(20.0)->appreciation)->toBe('Excellent')
        ->and(AppreciationScale::forAverage(18.0)->appreciation)->toBe('Excellent')
        ->and(AppreciationScale::forAverage(16.0)->appreciation)->toBe('Très bien')
        ->and(AppreciationScale::forAverage(14.0)->appreciation)->toBe('Bien')
        ->and(AppreciationScale::forAverage(6.0)->appreciation)->toBe('Insuffisant')
        ->and(AppreciationScale::forAverage(0.0)->appreciation)->toBe('Insuffisant');
});

test('une moyenne juste sous une tranche retombe sur la tranche inférieure', function () {
    // 15.9/20 = 79.5 % : sous les 80 % de « Très bien », retombe sur « Bien » (70 %).
    expect(AppreciationScale::forAverage(15.9)->appreciation)->toBe('Bien');
});

test('retourne null si aucune tranche ne correspond', function () {
    AppreciationScale::query()->delete();

    expect(AppreciationScale::forAverage(20.0))->toBeNull();
});

test('forAverage tient compte de l’échelle passée en second paramètre', function () {
    // 8/10 = 80 % → même tranche qu'une moyenne 16/20.
    expect(AppreciationScale::forAverage(8.0, 10.0)->appreciation)->toBe('Très bien')
        ->and(AppreciationScale::forAverage(5.0, 10.0)->appreciation)->toBe('Insuffisant');
});
