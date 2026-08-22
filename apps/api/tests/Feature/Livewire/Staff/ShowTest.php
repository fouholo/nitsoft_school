<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\Gender;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Livewire\Staff\Show;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('un LOCAL_ADMIN voit et modifie l’identité et les données professionnelles d’un membre', function () {
    $establishment = Establishment::factory()->create();
    $localAdmin = createLocalAdmin($establishment);
    test()->actingAs($localAdmin);

    $teacher = User::factory()->create();
    $establishment->users()->attach($teacher->id, ['role' => 'enseignant', 'is_active' => true]);
    $pivot = EstablishmentUserPivot::where('user_id', $teacher->id)->sole();

    Livewire::test(Show::class, ['establishment' => $establishment, 'pivot' => $pivot])
        ->assertOk()
        ->assertSet('canEditProfessional', true)
        ->set('gender', 'femme')
        ->set('birth_date', '1990-05-12')
        ->set('birth_place', 'Abidjan')
        ->set('nationality', 'Ivoirienne')
        ->set('city', 'Abidjan')
        ->set('matricule', 'MAT-001')
        ->set('job_title', 'Professeure de mathématiques')
        ->set('hired_at', '2020-09-01')
        ->set('education_level', 'Master')
        ->call('save')
        ->assertHasNoErrors();

    $teacher->refresh();
    $pivot->refresh();

    expect($teacher->gender)->toBe(Gender::Femme)
        ->and($teacher->birth_date->format('Y-m-d'))->toBe('1990-05-12')
        ->and($teacher->birth_place)->toBe('Abidjan')
        ->and($teacher->nationality)->toBe('Ivoirienne')
        ->and($teacher->city)->toBe('Abidjan')
        ->and($pivot->matricule)->toBe('MAT-001')
        ->and($pivot->job_title)->toBe('Professeure de mathématiques')
        ->and($pivot->hired_at->format('Y-m-d'))->toBe('2020-09-01')
        ->and($pivot->education_level)->toBe('Master');
});

test('le membre lui-même modifie son identité mais pas ses données professionnelles', function () {
    $establishment = Establishment::factory()->create();
    $teacher = User::factory()->create();
    $establishment->users()->attach($teacher->id, ['role' => 'enseignant', 'is_active' => true, 'matricule' => 'MAT-ORIGINAL']);
    $pivot = EstablishmentUserPivot::where('user_id', $teacher->id)->sole();
    test()->actingAs($teacher);

    Livewire::test(Show::class, ['establishment' => $establishment, 'pivot' => $pivot])
        ->assertOk()
        ->assertSet('canEditProfessional', false)
        ->set('city', 'Bouaké')
        ->set('matricule', 'MAT-TRICHE')
        ->call('save')
        ->assertHasNoErrors();

    expect($teacher->fresh()->city)->toBe('Bouaké')
        ->and($pivot->fresh()->matricule)->toBe('MAT-ORIGINAL');
});

test('un tiers non-admin et non concerné ne peut pas accéder à la fiche', function () {
    $establishment = Establishment::factory()->create();
    $teacher = User::factory()->create();
    $establishment->users()->attach($teacher->id, ['role' => 'enseignant', 'is_active' => true]);
    $pivot = EstablishmentUserPivot::where('user_id', $teacher->id)->sole();

    $otherTeacher = User::factory()->create();
    $establishment->users()->attach($otherTeacher->id, ['role' => 'enseignant', 'is_active' => true]);
    test()->actingAs($otherTeacher);

    Livewire::test(Show::class, ['establishment' => $establishment, 'pivot' => $pivot])->assertForbidden();
});

test('une date de naissance future est rejetée', function () {
    $establishment = Establishment::factory()->create();
    $localAdmin = createLocalAdmin($establishment);
    test()->actingAs($localAdmin);

    $teacher = User::factory()->create();
    $establishment->users()->attach($teacher->id, ['role' => 'enseignant', 'is_active' => true]);
    $pivot = EstablishmentUserPivot::where('user_id', $teacher->id)->sole();

    Livewire::test(Show::class, ['establishment' => $establishment, 'pivot' => $pivot])
        ->set('birth_date', now()->addDay()->format('Y-m-d'))
        ->call('save')
        ->assertHasErrors(['birth_date']);
});

test('un admin peut téléverser une photo pour un membre', function () {
    Storage::fake('public');

    $establishment = Establishment::factory()->create();
    $localAdmin = createLocalAdmin($establishment);
    test()->actingAs($localAdmin);

    $teacher = User::factory()->create();
    $establishment->users()->attach($teacher->id, ['role' => 'enseignant', 'is_active' => true]);
    $pivot = EstablishmentUserPivot::where('user_id', $teacher->id)->sole();

    Livewire::test(Show::class, ['establishment' => $establishment, 'pivot' => $pivot])
        ->set('photo', UploadedFile::fake()->image('photo.jpg')->size(50))
        ->call('save')
        ->assertHasNoErrors();

    $teacher->refresh();

    expect($teacher->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($teacher->photo_path);
});

test('remplacer la photo d’un membre supprime l’ancienne du stockage', function () {
    Storage::fake('public');
    Storage::disk('public')->put('staff-photos/old.jpg', 'contenu-factice');

    $establishment = Establishment::factory()->create();
    $localAdmin = createLocalAdmin($establishment);
    test()->actingAs($localAdmin);

    $teacher = User::factory()->create(['photo_path' => 'staff-photos/old.jpg']);
    $establishment->users()->attach($teacher->id, ['role' => 'enseignant', 'is_active' => true]);
    $pivot = EstablishmentUserPivot::where('user_id', $teacher->id)->sole();

    Livewire::test(Show::class, ['establishment' => $establishment, 'pivot' => $pivot])
        ->set('photo', UploadedFile::fake()->image('new.jpg')->size(50))
        ->call('save')
        ->assertHasNoErrors();

    $teacher->refresh();

    Storage::disk('public')->assertMissing('staff-photos/old.jpg');
    Storage::disk('public')->assertExists($teacher->photo_path);
});
