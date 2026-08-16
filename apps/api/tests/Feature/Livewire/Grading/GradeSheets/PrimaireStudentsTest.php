<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use App\Livewire\Grading\GradeSheets\Primaire\Students;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $this->directeur = createUserWithRole($this->establishment, 'directeur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->classroomA = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);
    $this->classroomB = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);
    $this->gradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => null,
        'subject_id' => null,
        'primary_subject_id' => null,
        'term_id' => null,
        'composition_number' => 1,
        'type' => 'composition',
    ]);
});

test('un directeur voit les élèves de toutes les classes primaire', function () {
    $studentA = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $studentA->id,
        'classroom_id' => $this->classroomA->id,
        'status' => 'active',
    ]);
    $studentB = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $studentB->id,
        'classroom_id' => $this->classroomB->id,
        'status' => 'active',
    ]);

    $students = Livewire::test(Students::class, ['gradeSheet' => $this->gradeSheet])->viewData('students');

    expect($students->pluck('id'))->toContain($studentA->id)
        ->toContain($studentB->id);
});

test('un enseignant ne voit que les élèves des classes auxquelles il est affecté', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    TeacherAssignment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'user_id' => $teacher->id,
        'classroom_id' => $this->classroomA->id,
        'subject_id' => null,
    ]);

    $studentA = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $studentA->id,
        'classroom_id' => $this->classroomA->id,
        'status' => 'active',
    ]);
    $studentB = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $studentB->id,
        'classroom_id' => $this->classroomB->id,
        'status' => 'active',
    ]);

    $this->actingAs($teacher);

    $students = Livewire::test(Students::class, ['gradeSheet' => $this->gradeSheet])->viewData('students');

    expect($students->pluck('id'))->toContain($studentA->id)
        ->not->toContain($studentB->id);
});

test('les élèves inactifs n’apparaissent pas', function () {
    $withdrawn = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $withdrawn->id,
        'classroom_id' => $this->classroomA->id,
        'status' => 'withdrawn',
    ]);

    $students = Livewire::test(Students::class, ['gradeSheet' => $this->gradeSheet])->viewData('students');

    expect($students->pluck('id'))->not->toContain($withdrawn->id);
});
