<?php

declare(strict_types=1);

namespace App\Livewire\Grading\GradeSheets;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Academics\Models\Term;
use App\Domain\Grading\Models\GradeSheet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Évaluations')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $classroom_id = null;

    public ?int $subject_id = null;

    public ?int $term_id = null;

    public string $title = '';

    public float $max_score = 20.0;

    public float $weight = 1.0;

    public string $graded_on = '';

    public function mount(): void
    {
        $this->authorize('viewAny', GradeSheet::class);
    }

    public function create(): void
    {
        $this->authorize('create', GradeSheet::class);

        $this->reset(['classroom_id', 'subject_id', 'term_id', 'title']);
        $this->max_score = 20.0;
        $this->weight = 1.0;
        $this->graded_on = now()->toDateString();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('create', GradeSheet::class);

        $data = $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'title' => ['required', 'string', 'max:255'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'weight' => ['required', 'numeric', 'min:0.5', 'max:20'],
            'graded_on' => ['required', 'date'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasAdminRightsOnCurrentEstablishment() && ! $user->isAssignedToClassroom((int) $data['classroom_id'], (int) $data['subject_id'])) {
            abort(403, "Vous n'êtes pas affecté à cette classe pour cette matière.");
        }

        $classroom = Classroom::findOrFail($data['classroom_id']);

        if (! $classroom->isGradable()) {
            throw ValidationException::withMessages([
                'classroom_id' => "Ce niveau n'a pas de notation.",
            ]);
        }

        GradeSheet::create([...$data, 'teacher_id' => $user->id]);

        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasAdminRightsOnCurrentEstablishment();

        $gradeSheets = GradeSheet::query()
            ->with(['classroom', 'subject', 'term'])
            ->when(! $isAdmin, fn ($query) => $query->where('teacher_id', $user->id))
            ->orderByDesc('graded_on')
            ->get();

        $assignments = TeacherAssignment::query()
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('classroom.level', fn ($query) => $query->where('cycle', '!=', Cycle::Prescolaire))
            ->with(['classroom', 'subject'])
            ->get();

        return view('livewire.grading.grade-sheets.index', [
            'gradeSheets' => $gradeSheets,
            'classrooms' => $isAdmin ? Classroom::gradable()->orderBy('name')->get() : $assignments->pluck('classroom')->unique('id'),
            'subjects' => $isAdmin ? Subject::orderBy('name')->get() : $assignments->pluck('subject')->unique('id'),
            'terms' => Term::orderBy('sequence')->get(),
        ]);
    }
}
