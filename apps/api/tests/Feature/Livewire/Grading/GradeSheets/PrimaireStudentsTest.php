<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Grading\Models\GradeSheet;
use App\Livewire\Grading\GradeSheets\Primaire\Students;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire]);
    $this->admin = createUserWithRole($this->establishment, 'educateur');
    actingInEstablishment($this->establishment);
    $this->actingAs($this->admin);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $this->establishment->id]);
    $this->classroom = Classroom::factory()->primaire()->create([
        'establishment_id' => $this->establishment->id,
        'school_year_id' => $schoolYear->id,
    ]);
    $this->gradeSheet = GradeSheet::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => $this->classroom->id,
        'subject_id' => null,
        'primary_subject_id' => null,
        'term_id' => null,
        'composition_number' => 1,
        'type' => 'composition',
    ]);
});

test('seuls les élèves inscrits et actifs de la classe apparaissent', function () {
    $active = Student::factory()->create(['establishment_id' => $this->establishment->id, 'last_name' => 'Actif']);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $active->id,
        'classroom_id' => $this->classroom->id,
        'status' => 'active',
    ]);

    $inactive = Student::factory()->create(['establishment_id' => $this->establishment->id, 'last_name' => 'Inactif']);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $inactive->id,
        'classroom_id' => $this->classroom->id,
        'status' => 'withdrawn',
    ]);

    $students = Livewire::test(Students::class, ['gradeSheet' => $this->gradeSheet])->viewData('students');

    expect($students->pluck('id'))->toContain($active->id)
        ->not->toContain($inactive->id);
});

test('un enseignant sans affectation sur la classe est refusé', function () {
    $teacher = createUserWithRole($this->establishment, 'enseignant');
    $this->actingAs($teacher);

    Livewire::test(Students::class, ['gradeSheet' => $this->gradeSheet])->assertForbidden();
});
