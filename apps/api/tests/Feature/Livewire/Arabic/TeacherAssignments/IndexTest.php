<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Arabic\TeacherAssignments\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['is_arabe' => true]);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);

    $this->teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->arabicSubject = ArabicSubject::factory()->create();
    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
    $this->arabicClassroom = ArabicClassroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
});

test('un directeur affecte un enseignant à une classe arabe, l’année venant de la classe', function () {
    $this->actingAs($this->directeur);

    Livewire::test(Index::class)
        ->set('user_id', $this->teacher->id)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->call('save')
        ->assertHasNoErrors();

    $assignment = ArabicTeacherAssignment::sole();

    expect($assignment->arabic_classroom_id)->toBe($this->arabicClassroom->id)
        ->and($assignment->user_id)->toBe($this->teacher->id)
        ->and($assignment->school_year_id)->toBe($this->schoolYear->id);
});

test('la classe arabe est obligatoire', function () {
    $this->actingAs($this->directeur);

    Livewire::test(Index::class)
        ->set('user_id', $this->teacher->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->call('save')
        ->assertHasErrors(['arabic_classroom_id']);

    expect(ArabicTeacherAssignment::count())->toBe(0);
});

test('une classe arabe d’un autre établissement est refusée', function () {
    $this->actingAs($this->directeur);

    $foreignClassroom = ArabicClassroom::factory()->create(['school_year_id' => $this->schoolYear->id]);

    Livewire::test(Index::class)
        ->set('user_id', $this->teacher->id)
        ->set('arabic_classroom_id', $foreignClassroom->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->call('save')
        ->assertHasErrors(['arabic_classroom_id']);

    expect(ArabicTeacherAssignment::withoutTenant()->count())->toBe(0);
});

test('seul un enseignant actif de l’établissement peut être affecté', function () {
    $this->actingAs($this->directeur);

    Livewire::test(Index::class)
        ->set('user_id', $this->directeur->id)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->call('save')
        ->assertHasErrors(['user_id']);
});

test('un fondateur d’un établissement indépendant peut affecter un enseignant', function () {
    $founder = createUserWithRole($this->establishment, 'fondateur');
    $this->actingAs($founder);

    Livewire::test(Index::class)
        ->set('user_id', $this->teacher->id)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(ArabicTeacherAssignment::count())->toBe(1);
});

test('un enseignant ne peut pas créer d’affectation', function () {
    $this->actingAs($this->teacher);

    Livewire::test(Index::class)
        ->set('user_id', $this->teacher->id)
        ->set('arabic_classroom_id', $this->arabicClassroom->id)
        ->set('arabic_subject_id', $this->arabicSubject->id)
        ->call('save')
        ->assertForbidden();

    expect(ArabicTeacherAssignment::count())->toBe(0);
});

test('un établissement non arabe n’a pas accès à l’écran', function () {
    $establishment = Establishment::factory()->create(['is_arabe' => false]);
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    $this->actingAs($directeur);

    Livewire::test(Index::class)->assertForbidden();
});

test('réaffecter le même enseignant à la même classe et matière ne crée pas de doublon', function () {
    $this->actingAs($this->directeur);

    foreach ([1, 2] as $attempt) {
        Livewire::test(Index::class)
            ->set('user_id', $this->teacher->id)
            ->set('arabic_classroom_id', $this->arabicClassroom->id)
            ->set('arabic_subject_id', $this->arabicSubject->id)
            ->call('save');
    }

    expect(ArabicTeacherAssignment::count())->toBe(1);
});
