<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\TeacherAssignments;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $user_id = null;

    public ?int $arabic_level_id = null;

    public ?int $arabic_serie_id = null;

    public ?int $arabic_subject_id = null;

    public ?int $school_year_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicTeacherAssignment::class);
    }

    public function create(): void
    {
        $this->authorize('create', ArabicTeacherAssignment::class);

        $this->reset(['user_id', 'arabic_level_id', 'arabic_serie_id', 'arabic_subject_id']);
        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->showForm = true;
    }

    public function updatedArabicLevelId(): void
    {
        $this->arabic_serie_id = null;
    }

    public function save(): void
    {
        $this->authorize('create', ArabicTeacherAssignment::class);

        $data = $this->validate([
            'user_id' => ['required', 'exists:users,id'],
            'arabic_level_id' => ['required', 'exists:arabic_levels,id'],
            'arabic_serie_id' => $this->selectedLevelRequiresSeries() ? ['required', 'exists:arabic_series,id'] : ['nullable'],
            'arabic_subject_id' => ['required', 'exists:arabic_subjects,id'],
            'school_year_id' => ['required', 'exists:school_years,id'],
        ]);

        if (! $this->selectedLevelRequiresSeries()) {
            $data['arabic_serie_id'] = null;
        }

        ArabicTeacherAssignment::firstOrCreate($data);

        $this->reset(['user_id', 'arabic_level_id', 'arabic_serie_id', 'arabic_subject_id']);
        $this->showForm = false;
    }

    public function selectedLevelRequiresSeries(): bool
    {
        return $this->arabic_level_id ? (bool) ArabicLevel::whereKey($this->arabic_level_id)->value('requires_series') : false;
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

    public function render()
    {
        $establishmentId = (int) app('currentEstablishmentId');

        return view('livewire.arabic.teacher-assignments.index', [
            'assignments' => ArabicTeacherAssignment::with(['teacher', 'arabicLevel', 'arabicSerie', 'arabicSubject', 'schoolYear'])
                ->orderByDesc('id')
                ->get(),
            'teachers' => Establishment::find($establishmentId)
                ->users()
                ->wherePivot('role', 'enseignant')
                ->wherePivot('is_active', true)
                ->get(),
            'arabicLevels' => ArabicLevel::orderBy('wording')->get(),
            'arabicSeries' => ArabicSerie::orderBy('serie')->get(),
            'arabicSubjects' => ArabicSubject::orderBy('name')->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
        ])->title(__('Affectations enseignants arabes'));
    }
}
