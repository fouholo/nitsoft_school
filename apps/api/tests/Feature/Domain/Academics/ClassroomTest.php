<?php

declare(strict_types=1);

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Establishments\Models\Establishment;

test('isGradable est faux pour le préscolaire et vrai pour les autres cycles', function () {
    $prescolaire = Classroom::factory()->prescolaire()->make();
    $primaire = Classroom::factory()->primaire()->make();
    $secondaire = Classroom::factory()->make();

    expect($prescolaire->isGradable())->toBeFalse()
        ->and($primaire->isGradable())->toBeTrue()
        ->and($secondaire->isGradable())->toBeTrue();
});

test('le scope gradable exclut les classes préscolaires', function () {
    $establishment = Establishment::factory()->create();

    $prescolaire = Classroom::factory()->prescolaire()->create(['establishment_id' => $establishment->id]);
    $primaire = Classroom::factory()->primaire()->create(['establishment_id' => $establishment->id]);
    $secondaire = Classroom::factory()->create(['establishment_id' => $establishment->id]);

    $gradable = Classroom::gradable()->pluck('id');

    expect($gradable)->toContain($primaire->id, $secondaire->id)
        ->not->toContain($prescolaire->id);
});

test('le cycle par défaut est secondaire', function () {
    $classroom = Classroom::factory()->create();

    expect($classroom->cycle)->toBe(Cycle::Secondaire);
});
