<?php

declare(strict_types=1);

namespace App\Livewire\Grading\GradeSheets\Secondaire;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Academics\Models\Term;
use App\Domain\Establishments\Support\RolePermissions;
use App\Domain\Grading\Models\GradeSheet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Index extends Component
{
    public bool $showForm = false;

    public ?int $classroom_id = null;

    public ?int $subject_id = null;

    public ?int $term_id = null;

    public string $title = '';

    public string $type = 'devoir';

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

        $this->reset(['classroom_id', 'subject_id', 'term_id', 'title', 'type']);
        $this->max_score = 20.0;
        $this->weight = 2.0;
        $this->graded_on = now()->toDateString();
        $this->showForm = true;
    }

    public function updatedClassroomId(): void
    {
        $this->type = 'devoir';
        $this->weight = 2.0;
        $this->subject_id = null;
        $this->term_id = null;
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
        $this->authorize('create', GradeSheet::class);

        $data = $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:devoir,interrogation'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'weight' => ['required', 'numeric', 'min:0.5', 'max:20'],
            'graded_on' => ['required', 'date'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $classroom = Classroom::findOrFail($data['classroom_id']);

        if ($classroom->level->cycle !== Cycle::Secondaire) {
            throw ValidationException::withMessages([
                'classroom_id' => __("Cette classe n'est pas une classe secondaire."),
            ]);
        }

        if (! $this->hasBroadGradeAccess($user) && ! $user->isAssignedToClassroom($classroom->id, (int) $data['subject_id'])) {
            abort(403, __("Vous n'êtes pas affecté à cette classe pour cette matière."));
        }

        if (! $classroom->isGradable()) {
            throw ValidationException::withMessages([
                'classroom_id' => __("Ce niveau n'a pas de notation."),
            ]);
        }

        $subject = Subject::findOrFail($data['subject_id']);

        if (! $subject->is_secondaire) {
            throw ValidationException::withMessages([
                'subject_id' => __("Cette matière n'est pas disponible pour ce cycle."),
            ]);
        }

        GradeSheet::create([...$data, 'teacher_id' => $user->id]);

        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    /**
     * Accès large aux évaluations (toutes classes/matières, pas seulement
     * ses propres affectations) : admin classique (vue seule depuis ce
     * chantier) ou educateur (vue + saisie) — voir
     * RolePermissions::MATRIX['grades.enter'].
     */
    private function hasBroadGradeAccess(User $user): bool
    {
        return $user->hasAdminRightsOnCurrentEstablishment()
            || RolePermissions::can($user->currentRole(), 'grades.enter');
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $this->hasBroadGradeAccess($user);

        $gradeSheets = GradeSheet::query()
            ->with(['classroom', 'subject', 'term'])
            ->whereHas('classroom.level', fn ($query) => $query->where('cycle', Cycle::Secondaire))
            ->when(! $isAdmin, fn ($query) => $query->where('teacher_id', $user->id))
            ->orderByDesc('graded_on')
            ->get();

        $assignments = TeacherAssignment::query()
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('classroom.level', fn ($query) => $query->where('cycle', Cycle::Secondaire))
            ->with(['classroom', 'subject'])
            ->get();

        $subjects = $isAdmin
            ? Subject::where('is_secondaire', true)->orderBy('name')->get()
            : $assignments->pluck('subject')->filter()->unique('id')->values();

        return view('livewire.grading.grade-sheets.secondaire.index', [
            'gradeSheets' => $gradeSheets,
            'classrooms' => $isAdmin
                ? Classroom::gradable()->whereHas('level', fn ($query) => $query->where('cycle', Cycle::Secondaire))->orderBy('name')->get()
                : $assignments->pluck('classroom')->unique('id'),
            'subjects' => $subjects,
            'terms' => Term::orderBy('sequence')->get(),
        ]);
    }
}
