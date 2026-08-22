<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\GradeSheets;

use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public bool $showForm = false;

    public ?int $arabic_level_id = null;

    public ?int $arabic_serie_id = null;

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

        $this->reset(['arabic_level_id', 'arabic_serie_id', 'arabic_subject_id', 'arabic_term_id', 'title', 'type']);
        $this->max_score = 20.0;
        $this->weight = 2.0;
        $this->graded_on = now()->toDateString();
        $this->showForm = true;
    }

    public function updatedArabicLevelId(): void
    {
        $this->arabic_serie_id = null;
    }

    public function updatedType(): void
    {
        $this->weight = match ($this->type) {
            'devoir' => 2.0,
            'interrogation' => 1.0,
            default => 1.0,
        };
    }

    public function selectedLevelRequiresSeries(): bool
    {
        return $this->arabic_level_id ? (bool) ArabicLevel::whereKey($this->arabic_level_id)->value('requires_series') : false;
    }

    public function save(): void
    {
        $this->authorize('create', ArabicGradeSheet::class);

        $data = $this->validate([
            'arabic_level_id' => ['required', 'exists:arabic_levels,id'],
            'arabic_serie_id' => $this->selectedLevelRequiresSeries() ? ['required', 'exists:arabic_series,id'] : ['nullable'],
            'arabic_subject_id' => ['required', 'exists:arabic_subjects,id'],
            'arabic_term_id' => ['required', 'exists:arabic_terms,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:devoir,interrogation'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'weight' => ['required', 'numeric', 'min:0.5', 'max:20'],
            'graded_on' => ['required', 'date'],
        ]);

        if (! $this->selectedLevelRequiresSeries()) {
            $data['arabic_serie_id'] = null;
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $this->hasBroadAccess($user) && ! $user->isAssignedToArabicGroup((int) $data['arabic_level_id'], $data['arabic_serie_id'], (int) $data['arabic_subject_id'])) {
            abort(403, __("Vous n'êtes pas affecté à ce groupe pour cette matière."));
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
            ->with(['arabicLevel', 'arabicSerie', 'arabicSubject', 'arabicTerm'])
            ->when(! $isAdmin, fn ($query) => $query->where('teacher_id', $user->id))
            ->orderByDesc('graded_on')
            ->get();

        $assignments = ArabicTeacherAssignment::query()
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
            ->with(['arabicLevel', 'arabicSerie', 'arabicSubject'])
            ->get();

        return view('livewire.arabic.grade-sheets.index', [
            'gradeSheets' => $gradeSheets,
            'arabicLevels' => $isAdmin
                ? ArabicLevel::orderBy('wording')->get()
                : $assignments->pluck('arabicLevel')->filter()->unique('id')->values(),
            'arabicSeries' => ArabicSerie::orderBy('serie')->get(),
            'arabicSubjects' => $isAdmin
                ? ArabicSubject::orderBy('name')->get()
                : $assignments->pluck('arabicSubject')->filter()->unique('id')->values(),
            'arabicTerms' => ArabicTerm::orderBy('sequence')->get(),
        ])->title(__('Évaluations arabes'));
    }
}
