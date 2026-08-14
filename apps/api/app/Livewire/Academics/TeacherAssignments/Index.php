<?php

declare(strict_types=1);

namespace App\Livewire\Academics\TeacherAssignments;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Affectations enseignants')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $user_id = null;

    public ?int $classroom_id = null;

    public ?int $subject_id = null;

    public ?int $school_year_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', TeacherAssignment::class);
    }

    public function create(): void
    {
        $this->authorize('create', TeacherAssignment::class);

        $this->reset(['user_id', 'classroom_id', 'subject_id']);
        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->showForm = true;
    }

    public function updatedClassroomId(): void
    {
        $this->subject_id = null;
    }

    public function save(): void
    {
        $this->authorize('create', TeacherAssignment::class);

        $isSecondaire = $this->selectedClassroomCycle() === Cycle::Secondaire;

        $data = $this->validate([
            'user_id' => ['required', 'exists:users,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'subject_id' => $isSecondaire ? ['required', 'exists:subjects,id'] : ['nullable'],
            'school_year_id' => ['required', 'exists:school_years,id'],
        ]);

        if (! $isSecondaire) {
            $data['subject_id'] = null;
        }

        TeacherAssignment::firstOrCreate($data);

        $this->reset(['user_id', 'classroom_id', 'subject_id']);
        $this->showForm = false;
    }

    public function selectedClassroomCycle(): ?Cycle
    {
        return $this->classroom_id ? Classroom::find($this->classroom_id)?->level?->cycle : null;
    }

    public function delete(int $assignmentId): void
    {
        $assignment = TeacherAssignment::findOrFail($assignmentId);

        $this->authorize('delete', $assignment);

        $assignment->delete();
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        $establishmentId = (int) app('currentEstablishmentId');

        return view('livewire.academics.teacher-assignments.index', [
            'assignments' => TeacherAssignment::with(['teacher', 'classroom', 'subject', 'schoolYear'])
                ->orderByDesc('id')
                ->get(),
            'teachers' => Establishment::find($establishmentId)
                ->users()
                ->wherePivot('role', 'enseignant')
                ->wherePivot('is_active', true)
                ->get(),
            'classrooms' => Classroom::orderBy('name')->get(),
            'subjects' => Subject::orderBy('name')->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
        ]);
    }
}
