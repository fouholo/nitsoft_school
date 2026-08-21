<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Academics\TeacherAssignments\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->admin = createLocalAdmin($this->establishment);
    $this->teacher = createUserWithRole($this->establishment, 'enseignant');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $this->schoolYear = SchoolYear::factory()->create(['is_current' => true]);
});

test('affecter un enseignant à une classe primaire ne demande pas de matière', function () {
    $classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    Livewire::test(Index::class)
        ->call('create')
        ->set('user_id', $this->teacher->id)
        ->set('classroom_id', $classroom->id)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    $assignment = TeacherAssignment::sole();

    expect($assignment->classroom_id)->toBe($classroom->id)
        ->and($assignment->subject_id)->toBeNull();
});

test('affecter un enseignant à une classe secondaire sans matière est rejeté', function () {
    $classroom = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    Livewire::test(Index::class)
        ->call('create')
        ->set('user_id', $this->teacher->id)
        ->set('classroom_id', $classroom->id)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasErrors(['subject_id']);

    expect(TeacherAssignment::count())->toBe(0);
});

test('affecter un enseignant à une classe secondaire avec matière fonctionne', function () {
    $classroom = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    $subject = Subject::factory()->create();

    Livewire::test(Index::class)
        ->call('create')
        ->set('user_id', $this->teacher->id)
        ->set('classroom_id', $classroom->id)
        ->set('subject_id', $subject->id)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save')
        ->assertHasNoErrors();

    $assignment = TeacherAssignment::sole();

    expect($assignment->subject_id)->toBe($subject->id);
});

test('changer de classe réinitialise la matière sélectionnée', function () {
    $primaire = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    $secondaire = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);
    $subject = Subject::factory()->create();

    Livewire::test(Index::class)
        ->call('create')
        ->set('classroom_id', $secondaire->id)
        ->set('subject_id', $subject->id)
        ->set('classroom_id', $primaire->id)
        ->assertSet('subject_id', null);
});

test('ré-affecter le même enseignant à la même classe primaire ne crée pas de doublon', function () {
    $classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $this->schoolYear->id,
    ]);

    Livewire::test(Index::class)
        ->call('create')
        ->set('user_id', $this->teacher->id)
        ->set('classroom_id', $classroom->id)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save');

    Livewire::test(Index::class)
        ->call('create')
        ->set('user_id', $this->teacher->id)
        ->set('classroom_id', $classroom->id)
        ->set('school_year_id', $this->schoolYear->id)
        ->call('save');

    expect(TeacherAssignment::count())->toBe(1);
});
