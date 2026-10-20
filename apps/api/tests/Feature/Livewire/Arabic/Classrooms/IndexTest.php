<?php

declare(strict_types=1);

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\Classrooms\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['type' => EstablishmentType::Secondaire, 'is_arabe' => true]);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->directeur);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->arabicLevel = ArabicLevel::factory()->create([
        'wording' => 'الصف الأول',
        'wording_fr' => '1ère année',
        'cycle' => Cycle::Secondaire->value,
        'requires_series' => false,
    ]);
});

test('un directeur crée une classe arabe dont le nom est généré', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->assertSet('school_year_id', $this->schoolYear->id)
        ->set('arabic_level_id', $this->arabicLevel->id)
        ->set('numero', '2')
        ->set('capacity', 35)
        ->call('save')
        ->assertHasNoErrors();

    $arabicClassroom = ArabicClassroom::sole();

    expect($arabicClassroom->name)->toBe('1ère année 2')
        ->and($arabicClassroom->establishment_id)->toBe($this->establishment->id)
        ->and($arabicClassroom->school_year_id)->toBe($this->schoolYear->id)
        ->and($arabicClassroom->capacity)->toBe(35)
        ->and($arabicClassroom->arabic_serie_id)->toBeNull();
});

test('la série est obligatoire quand le niveau arabe l’exige', function () {
    $levelWithSeries = ArabicLevel::factory()->create(['cycle' => Cycle::Secondaire->value, 'requires_series' => true]);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $levelWithSeries->id)
        ->set('numero', '1')
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasErrors(['arabic_serie_id']);

    $serie = ArabicSerie::factory()->create(['serie' => 'L']);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $levelWithSeries->id)
        ->set('arabic_serie_id', $serie->id)
        ->set('numero', '1')
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicClassroom::sole()->arabic_serie_id)->toBe($serie->id);
});

test('une série transmise pour un niveau qui n’en exige pas est ignorée', function () {
    Livewire::test(Index::class)
        ->set('arabic_level_id', $this->arabicLevel->id)
        ->set('arabic_serie_id', ArabicSerie::factory()->create()->id)
        ->set('numero', '1')
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicClassroom::sole()->arabic_serie_id)->toBeNull();
});

test('une classe arabe identique pour la même année est refusée, y compris sans série', function () {
    ArabicClassroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicLevel->id,
        'numero' => '1',
    ]);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $this->arabicLevel->id)
        ->set('numero', '1')
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasErrors(['numero']);

    expect(ArabicClassroom::count())->toBe(1);
});

test('modifier une classe arabe ne la considère pas comme un doublon d’elle-même', function () {
    $arabicClassroom = ArabicClassroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
        'arabic_level_id' => $this->arabicLevel->id,
        'numero' => '1',
    ]);

    Livewire::test(Index::class)
        ->call('edit', $arabicClassroom->id)
        ->set('capacity', 40)
        ->call('save')
        ->assertHasNoErrors();

    expect($arabicClassroom->refresh()->capacity)->toBe(40);
});

test('un niveau arabe d’un cycle non proposé par l’établissement est refusé', function () {
    $primaryLevel = ArabicLevel::factory()->create(['cycle' => Cycle::Primaire->value]);

    Livewire::test(Index::class)
        ->set('arabic_level_id', $primaryLevel->id)
        ->set('numero', 'A')
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasErrors(['arabic_level_id']);

    expect(ArabicClassroom::count())->toBe(0);
});

test('une classe arabe qui a des élèves inscrits ne peut pas être supprimée', function () {
    $arabicClassroom = ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'arabic_classroom_id' => $arabicClassroom->id,
        'status' => 'withdrawn',
    ]);

    Livewire::test(Index::class)
        ->call('delete', $arabicClassroom->id)
        ->assertHasErrors(['delete']);

    expect(ArabicClassroom::whereKey($arabicClassroom->id)->exists())->toBeTrue();
});

test('une classe arabe sans élève peut être supprimée', function () {
    $arabicClassroom = ArabicClassroom::factory()->create(['establishment_id' => $this->establishment->id]);

    Livewire::test(Index::class)
        ->call('delete', $arabicClassroom->id)
        ->assertHasNoErrors();

    expect(ArabicClassroom::count())->toBe(0);
});

test('un enseignant consulte les classes arabes mais ne peut pas en créer', function () {
    $this->actingAs(createUserWithRole($this->establishment, 'enseignant'));

    Livewire::test(Index::class)
        ->assertOk()
        ->assertDontSee(__('Nouvelle classe arabe'))
        ->call('create')
        ->assertForbidden();
});

test('les classes arabes d’un autre établissement restent invisibles', function () {
    ArabicClassroom::factory()->create(['arabic_level_id' => $this->arabicLevel->id, 'numero' => '9']);

    Livewire::test(Index::class)->assertDontSee('1ère année 9');
});

test('un établissement non arabe n’a pas accès à l’écran', function () {
    $establishment = Establishment::factory()->create(['is_arabe' => false]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($directeur);

    Livewire::test(Index::class)->assertForbidden();
});

test('la route des classes arabes est accessible à une école franco-arabe', function () {
    $this->withSession(['current_establishment_id' => $this->establishment->id])
        ->get(route('arabic.classrooms.index'))
        ->assertOk();
});

test('les numéros proposés suivent le cycle du niveau arabe (lettres au primaire, chiffres au secondaire)', function () {
    $component = Livewire::test(Index::class)->set('arabic_level_id', $this->arabicLevel->id);

    expect($component->instance()->numeroOptions())->toBe(array_map('strval', range(1, 10)));

    $primaryLevel = ArabicLevel::factory()->create(['cycle' => Cycle::Primaire->value]);

    expect($component->set('arabic_level_id', $primaryLevel->id)->instance()->numeroOptions())->toBe(['A', 'B', 'C', 'D', 'E', 'F']);
});
