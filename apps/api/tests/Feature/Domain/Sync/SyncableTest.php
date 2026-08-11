<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use Illuminate\Support\Facades\Schema;

test('un modèle Syncable reçoit automatiquement un uid_local et un uid_serveur préfixé à la création', function () {
    $classroom = Classroom::factory()->create();

    expect($classroom->uid_local)->not->toBeNull()
        ->and($classroom->uid_local)->toHaveLength(20)
        ->and($classroom->uid_serveur)->not->toBeNull()
        ->and($classroom->uid_serveur)->toMatch('/^212\d{9}$/');
});

test('les tables calculées côté serveur n’ont pas de colonne uid_local/uid_serveur', function () {
    expect(Schema::hasColumn('report_cards', 'uid_local'))->toBeFalse()
        ->and(Schema::hasColumn('receipts', 'uid_local'))->toBeFalse()
        ->and(Schema::hasColumn('sms_messages', 'uid_local'))->toBeFalse();
});
