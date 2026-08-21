<?php

declare(strict_types=1);

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicSubjectCoefficient;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Models\Establishment;

test('ArabicLevel caste le cycle et requires_series correctement', function () {
    $arabicLevel = ArabicLevel::factory()->create(['cycle' => Cycle::Secondaire->value, 'requires_series' => true]);

    expect($arabicLevel->cycle)->toBe(Cycle::Secondaire)
        ->and($arabicLevel->requires_series)->toBeTrue();
});

test('ArabicSubjectCoefficient est rattaché à son niveau, sa série et sa matière', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $arabicLevel = ArabicLevel::factory()->create();
    $arabicSerie = ArabicSerie::factory()->create();
    $arabicSubject = ArabicSubject::factory()->create();

    $coefficient = ArabicSubjectCoefficient::factory()->create([
        'establishment_id' => $establishment->id,
        'arabic_level_id' => $arabicLevel->id,
        'arabic_serie_id' => $arabicSerie->id,
        'arabic_subject_id' => $arabicSubject->id,
        'coefficient' => 2.5,
    ]);

    expect($coefficient->arabicLevel->is($arabicLevel))->toBeTrue()
        ->and($coefficient->arabicSerie->is($arabicSerie))->toBeTrue()
        ->and($coefficient->arabicSubject->is($arabicSubject))->toBeTrue()
        ->and($coefficient->uid_serveur)->toMatch('/^251\d{9}$/');
});

test('un même établissement × niveau × série × matière est déduplié via updateOrCreate, pas une contrainte DB', function () {
    // Comme subject_coefficients côté français : la table ne porte qu'un
    // index de recherche (arabic_serie_id étant nullable, une contrainte
    // unique SQL ne détecterait de toute façon pas les doublons "sans
    // série", NULL n'étant jamais égal à NULL). L'unicité applicative est
    // assurée par l'écran Livewire via updateOrCreate() — voir
    // Livewire\Arabic\SubjectCoefficients\IndexTest « re-enregistrer un
    // coefficient... le remplace ».
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $arabicLevel = ArabicLevel::factory()->create();
    $arabicSubject = ArabicSubject::factory()->create();

    ArabicSubjectCoefficient::updateOrCreate(
        ['establishment_id' => $establishment->id, 'arabic_level_id' => $arabicLevel->id, 'arabic_serie_id' => null, 'arabic_subject_id' => $arabicSubject->id],
        ['coefficient' => 2],
    );
    ArabicSubjectCoefficient::updateOrCreate(
        ['establishment_id' => $establishment->id, 'arabic_level_id' => $arabicLevel->id, 'arabic_serie_id' => null, 'arabic_subject_id' => $arabicSubject->id],
        ['coefficient' => 4],
    );

    expect(ArabicSubjectCoefficient::count())->toBe(1)
        ->and((float) ArabicSubjectCoefficient::sole()->coefficient)->toBe(4.0);
});

test('le catalogue ArabicLevel/ArabicSerie/ArabicSubject est partagé entre établissements, les coefficients non', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();

    $arabicLevel = ArabicLevel::factory()->create();
    $arabicSubject = ArabicSubject::factory()->create();

    actingInEstablishment($establishmentA);
    ArabicSubjectCoefficient::factory()->create([
        'establishment_id' => $establishmentA->id,
        'arabic_level_id' => $arabicLevel->id,
        'arabic_subject_id' => $arabicSubject->id,
    ]);

    actingInEstablishment($establishmentB);

    expect(ArabicLevel::whereKey($arabicLevel->id)->exists())->toBeTrue()
        ->and(ArabicSubject::whereKey($arabicSubject->id)->exists())->toBeTrue()
        ->and(ArabicSubjectCoefficient::where('establishment_id', $establishmentB->id)->count())->toBe(0)
        ->and(ArabicSubjectCoefficient::withoutTenant()->where('establishment_id', $establishmentA->id)->count())->toBe(1);
});

test('une inscription peut porter un niveau et une série arabes indépendants de sa classe française', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => true]);
    $arabicSerie = ArabicSerie::factory()->create();

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'arabic_level_id' => $arabicLevel->id,
        'arabic_serie_id' => $arabicSerie->id,
    ]);

    expect($enrollment->arabicLevel->is($arabicLevel))->toBeTrue()
        ->and($enrollment->arabicSerie->is($arabicSerie))->toBeTrue();
});

test('arabic_level_id et arabic_serie_id restent nuls pour une inscription hors filière arabe', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $enrollment = Enrollment::factory()->create(['establishment_id' => $establishment->id]);

    expect($enrollment->arabic_level_id)->toBeNull()
        ->and($enrollment->arabic_serie_id)->toBeNull();
});
