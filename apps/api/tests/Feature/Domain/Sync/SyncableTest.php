<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use Illuminate\Support\Facades\Schema;

test('un modèle Syncable reçoit automatiquement un uid à la création', function () {
    $classroom = Classroom::factory()->create();

    expect($classroom->uid)->not->toBeNull()
        ->and($classroom->uid)->toMatch('/^\d{12}$/');
});

test('les tables calculées côté serveur n’ont pas de colonne uid', function () {
    expect(Schema::hasColumn('report_cards', 'uid'))->toBeFalse()
        ->and(Schema::hasColumn('receipts', 'uid'))->toBeFalse()
        ->and(Schema::hasColumn('sms_messages', 'uid'))->toBeFalse();
});
