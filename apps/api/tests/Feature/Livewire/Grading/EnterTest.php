<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\Grade;
use App\Domain\Grading\Models\GradeSheet;
use App\Livewire\Grading\GradeSheets\Enter;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    // educateur : seul rôle admin-tier habilité à saisir des notes depuis le
    // chantier "privilèges par rôle" (RolePermissions::MATRIX['grades.enter']).
    $this->admin = createUserWithRole($this->establishment, 'educateur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $schoolYear = SchoolYear::factory()->create();
    $this->classroom = Classroom::factory()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);
    $this->student = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $this->student->id,
        'classroom_id' => $this->classroom->id,
        'status' => 'active',
    ]);
    $this->gradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => $this->classroom->id,
        'max_score' => 20,
    ]);
});

test('une note vide n’est pas enregistrée comme une chaîne vide', function () {
    // Même classe de bug que Students\Index : un champ vide doit devenir null.
    Livewire::test(Enter::class, ['gradeSheet' => $this->gradeSheet])
        ->set("scores.{$this->student->id}", '')
        ->call('save')
        ->assertHasNoErrors();

    $grade = Grade::sole();

    expect($grade->score)->toBeNull();
});

test('une note est enregistrée et bornée par le barème', function () {
    Livewire::test(Enter::class, ['gradeSheet' => $this->gradeSheet])
        ->set("scores.{$this->student->id}", '15.5')
        ->call('save')
        ->assertHasNoErrors();

    expect((float) Grade::sole()->score)->toBe(15.5);
});

test('une note supérieure au barème est rejetée', function () {
    Livewire::test(Enter::class, ['gradeSheet' => $this->gradeSheet])
        ->set("scores.{$this->student->id}", '25')
        ->call('save')
        ->assertHasErrors("scores.{$this->student->id}");

    expect(Grade::count())->toBe(0);
});

test('le nombre d’élèves notés est affiché et se met à jour', function () {
    $otherStudent = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $otherStudent->id,
        'classroom_id' => $this->classroom->id,
        'status' => 'active',
    ]);

    Livewire::test(Enter::class, ['gradeSheet' => $this->gradeSheet])
        ->assertSee('0/2 notées')
        ->set("scores.{$this->student->id}", '12')
        ->assertSee('1/2 notées');
});
