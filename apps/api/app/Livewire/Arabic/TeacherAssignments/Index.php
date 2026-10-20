<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\TeacherAssignments;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $user_id = null;

    public ?int $arabic_classroom_id = null;

    public ?int $arabic_subject_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicTeacherAssignment::class);
    }

    public function create(): void
    {
        $this->authorize('create', ArabicTeacherAssignment::class);

        $this->reset(['user_id', 'arabic_classroom_id', 'arabic_subject_id']);
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('create', ArabicTeacherAssignment::class);

        $establishmentId = (int) app('currentEstablishmentId');

        $data = $this->validate([
            'user_id' => ['required', Rule::in($this->teachers()->pluck('id')->all())],
            'arabic_classroom_id' => ['required', Rule::exists('arabic_classrooms', 'id')->where('establishment_id', $establishmentId)],
            'arabic_subject_id' => ['required', 'exists:arabic_subjects,id'],
        ], [], [
            'user_id' => __('enseignant'),
            'arabic_classroom_id' => __('classe arabe'),
            'arabic_subject_id' => __('matière arabe'),
        ]);

        // L'année de l'affectation est celle de la classe : une classe
        // arabe n'existe que pour une année scolaire.
        $data['school_year_id'] = ArabicClassroom::findOrFail($data['arabic_classroom_id'])->school_year_id;

        ArabicTeacherAssignment::firstOrCreate($data);

        $this->reset(['user_id', 'arabic_classroom_id', 'arabic_subject_id']);
        $this->showForm = false;
    }

    public function delete(int $assignmentId): void
    {
        $assignment = ArabicTeacherAssignment::findOrFail($assignmentId);

        $this->authorize('delete', $assignment);

        $assignment->delete();
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    /**
     * @return Collection<int, User>
     */
    private function teachers()
    {
        return Establishment::findOrFail((int) app('currentEstablishmentId'))
            ->users()
            ->wherePivot('role', 'enseignant')
            ->wherePivot('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.arabic.teacher-assignments.index', [
            'assignments' => ArabicTeacherAssignment::with(['teacher', 'arabicClassroom', 'arabicSubject', 'schoolYear'])
                ->orderByDesc('id')
                ->get(),
            'teachers' => $this->teachers(),
            'arabicClassrooms' => ArabicClassroom::query()
                ->whereHas('schoolYear', fn ($query) => $query->where('is_current', true))
                ->orderBy('name')
                ->get(),
            'arabicSubjects' => ArabicSubject::orderBy('name')->get(),
        ])->title(__('Affectations enseignants arabes'));
    }
}
