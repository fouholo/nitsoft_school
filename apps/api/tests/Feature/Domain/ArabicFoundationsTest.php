<?php

declare(strict_types=1);

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicSubjectCoefficient;
use App\Domain\Enrollment\Enums\Track;
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

test('une inscription porte une classe arabe en plus de sa classe française', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $arabicLevel = ArabicLevel::factory()->create(['requires_series' => true]);
    $arabicSerie = ArabicSerie::factory()->create();
    $arabicClassroom = ArabicClassroom::factory()->create([
        'establishment_id' => $establishment->id,
        'arabic_level_id' => $arabicLevel->id,
        'arabic_serie_id' => $arabicSerie->id,
    ]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => Classroom::factory()->create(['establishment_id' => $establishment->id])->id,
        'arabic_classroom_id' => $arabicClassroom->id,
    ])->refresh();

    expect($enrollment->arabicClassroom->is($arabicClassroom))->toBeTrue()
        ->and($enrollment->arabicClassroom->arabicLevel->is($arabicLevel))->toBeTrue()
        ->and($enrollment->arabicClassroom->arabicSerie->is($arabicSerie))->toBeTrue()
        ->and($enrollment->main_track)->toBe(Track::Francais)
        ->and($enrollment->mainClassroom()?->is($enrollment->classroom))->toBeTrue()
        ->and($enrollment->secondaryClassroom()?->is($arabicClassroom))->toBeTrue();
});

test('une inscription dont la filière principale est arabe peut ne pas avoir de classe française', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $arabicClassroom = ArabicClassroom::factory()->create(['establishment_id' => $establishment->id]);

    $enrollment = Enrollment::factory()->create([
        'establishment_id' => $establishment->id,
        'classroom_id' => null,
        'arabic_classroom_id' => $arabicClassroom->id,
        'main_track' => Track::Arabe,
    ]);

    expect($enrollment->mainClassroom()?->is($arabicClassroom))->toBeTrue()
        ->and($enrollment->secondaryClassroom())->toBeNull()
        ->and($enrollment->mainClassroomName())->toBe($arabicClassroom->name);
});

test('le nom de la classe arabe reprend le libellé français du niveau, la série et le numéro', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $arabicLevel = ArabicLevel::factory()->create(['wording' => 'الصف الأول', 'wording_fr' => '1ère année', 'requires_series' => true]);
    $arabicSerie = ArabicSerie::factory()->create(['serie' => 'L']);

    $arabicClassroom = ArabicClassroom::factory()->create([
        'establishment_id' => $establishment->id,
        'arabic_level_id' => $arabicLevel->id,
        'arabic_serie_id' => $arabicSerie->id,
        'numero' => 'A',
    ]);

    expect($arabicClassroom->name)->toBe('1ère année L A')
        ->and($arabicClassroom->uid_serveur)->toMatch('/^255\d{9}$/');

    $arabicLevel->update(['wording_fr' => null]);
    $arabicClassroom->update(['arabic_serie_id' => null, 'numero' => 'B']);

    expect($arabicClassroom->refresh()->name)->toBe('الصف الأول B');
});
