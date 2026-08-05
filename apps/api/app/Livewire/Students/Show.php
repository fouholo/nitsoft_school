<?php

declare(strict_types=1);

namespace App\Livewire\Students;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Fiche élève')]
class Show extends Component
{
    public Student $student;

    public bool $showEnrollmentForm = false;

    public ?int $classroom_id = null;

    public ?int $school_year_id = null;

    public string $enrolled_on = '';

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);

        $this->student = $student;
    }

    public function addEnrollment(): void
    {
        $this->authorize('create', Enrollment::class);

        $this->classroom_id = null;
        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->enrolled_on = now()->toDateString();
        $this->showEnrollmentForm = true;
    }

    public function saveEnrollment(): void
    {
        $this->authorize('create', Enrollment::class);

        $data = $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'school_year_id' => ['required', 'exists:school_years,id'],
            'enrolled_on' => ['required', 'date'],
        ]);

        $this->student->enrollments()->create([
            ...$data,
            'status' => 'active',
        ]);

        $this->showEnrollmentForm = false;
    }

    public function cancelEnrollment(): void
    {
        $this->showEnrollmentForm = false;
    }

    public function withdrawEnrollment(int $enrollmentId): void
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);

        $this->authorize('update', $enrollment);

        $enrollment->update(['status' => 'withdrawn']);
    }

    public function render()
    {
        return view('livewire.students.show', [
            'enrollments' => $this->student->enrollments()->with(['classroom', 'schoolYear'])->latest('enrolled_on')->get(),
            'classrooms' => Classroom::orderBy('name')->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
        ]);
    }
}
