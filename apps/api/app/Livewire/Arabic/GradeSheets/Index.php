<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\GradeSheets;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Index extends Component
{
    public bool $showForm = false;

    public ?int $arabic_classroom_id = null;

    public ?int $arabic_subject_id = null;

    public ?int $arabic_term_id = null;

    public string $title = '';

    public string $type = 'devoir';

    public float $max_score = 20.0;

    public float $weight = 2.0;

    public string $graded_on = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicGradeSheet::class);
    }

    public function create(): void
    {
        $this->authorize('create', ArabicGradeSheet::class);

        $this->reset(['arabic_classroom_id', 'arabic_subject_id', 'arabic_term_id', 'title', 'type']);
        $this->max_score = 20.0;
        $this->weight = 2.0;
        $this->graded_on = now()->toDateString();
        $this->showForm = true;
    }

    public function updatedType(): void
    {
        $this->weight = match ($this->type) {
            'devoir' => 2.0,
            'interrogation' => 1.0,
            default => 1.0,
        };
    }

    public function save(): void
    {
        $this->authorize('create', ArabicGradeSheet::class);

        $data = $this->validate([
            'arabic_classroom_id' => ['required', Rule::exists('arabic_classrooms', 'id')->where('establishment_id', (int) app('currentEstablishmentId'))],
            'arabic_subject_id' => ['required', 'exists:arabic_subjects,id'],
            'arabic_term_id' => ['required', 'exists:arabic_terms,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:devoir,interrogation'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'weight' => ['required', 'numeric', 'min:0.5', 'max:20'],
            'graded_on' => ['required', 'date'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! $this->hasBroadAccess($user) && ! $user->isAssignedToArabicClassroom((int) $data['arabic_classroom_id'], (int) $data['arabic_subject_id'])) {
            abort(403, __("Vous n'êtes pas affecté à cette classe pour cette matière."));
        }

        ArabicGradeSheet::create([...$data, 'teacher_id' => $user->id]);

        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    private function hasBroadAccess(User $user): bool
    {
        return RolePermissions::can($user->currentRole(), 'grades.enter');
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $this->hasBroadAccess($user);

        $gradeSheets = ArabicGradeSheet::query()
            ->with(['arabicClassroom', 'arabicSubject', 'arabicTerm'])
            ->when(! $isAdmin, fn ($query) => $query->where('teacher_id', $user->id))
            ->orderByDesc('graded_on')
            ->get();

        $assignments = ArabicTeacherAssignment::query()
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
            ->with(['arabicClassroom', 'arabicSubject'])
            ->get();

        return view('livewire.arabic.grade-sheets.index', [
            'gradeSheets' => $gradeSheets,
            'arabicClassrooms' => $isAdmin
                ? ArabicClassroom::whereHas('schoolYear', fn ($query) => $query->where('is_current', true))->orderBy('name')->get()
                : $assignments->pluck('arabicClassroom')->filter()->unique('id')->sortBy('name')->values(),
            'arabicSubjects' => $isAdmin
                ? ArabicSubject::orderBy('name')->get()
                : $assignments->pluck('arabicSubject')->filter()->unique('id')->values(),
            'arabicTerms' => ArabicTerm::orderBy('sequence')->get(),
        ])->title(__('Évaluations arabes'));
    }
}
