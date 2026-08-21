<?php

declare(strict_types=1);

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Attendance\Sessions\Mark;
use Livewire\Livewire;

beforeEach(function () {
    $this->establishment = Establishment::factory()->create();
    $this->admin = createUserWithRole($this->establishment, 'directeur');
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
    $this->session = AttendanceSession::factory()->create([
        'establishment_id' => $this->establishment->id,
        'classroom_id' => $this->classroom->id,
    ]);
});

test('les présences sont enregistrées pour chaque élève inscrit', function () {
    Livewire::test(Mark::class, ['session' => $this->session])
        ->set("statuses.{$this->student->id}", 'absent')
        ->set("notes.{$this->student->id}", 'Justifié par les parents')
        ->call('save')
        ->assertHasNoErrors();

    $record = AttendanceRecord::sole();

    expect($record->status)->toBe('absent')
        ->and($record->note)->toBe('Justifié par les parents')
        ->and($record->student_id)->toBe($this->student->id);
});

test('un statut invalide est rejeté', function () {
    Livewire::test(Mark::class, ['session' => $this->session])
        ->set("statuses.{$this->student->id}", 'en-vacances')
        ->call('save')
        ->assertHasErrors("statuses.{$this->student->id}");

    expect(AttendanceRecord::count())->toBe(0);
});

test('marquer tout absent bascule le statut de tous les élèves affichés', function () {
    $otherStudent = Student::factory()->create(['establishment_id' => $this->establishment->id]);
    Enrollment::factory()->create([
        'establishment_id' => $this->establishment->id,
        'student_id' => $otherStudent->id,
        'classroom_id' => $this->classroom->id,
        'status' => 'active',
    ]);

    Livewire::test(Mark::class, ['session' => $this->session])
        ->assertSet("statuses.{$this->student->id}", 'present')
        ->call('markAll', 'absent')
        ->assertSet("statuses.{$this->student->id}", 'absent')
        ->assertSet("statuses.{$otherStudent->id}", 'absent')
        ->call('save')
        ->assertHasNoErrors();

    expect(AttendanceRecord::where('status', 'absent')->count())->toBe(2);
});
