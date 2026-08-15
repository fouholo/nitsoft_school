<?php

declare(strict_types=1);

namespace App\Livewire\Grading\GradeSheets;

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

    public ?int $composition_number = null;

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

        $this->reset(['classroom_id', 'subject_id', 'term_id', 'composition_number', 'title', 'type']);
        $this->max_score = 20.0;
        $this->weight = 2.0;
        $this->graded_on = now()->toDateString();
        $this->showForm = true;
    }

    public function updatedClassroomId(): void
    {
        if ($this->selectedClassroomCycle() === Cycle::Primaire) {
            $this->type = 'composition';
            $this->weight = 1.0;
        } else {
            $this->type = 'devoir';
            $this->weight = 2.0;
        }

        $this->subject_id = null;
        $this->term_id = null;
        $this->composition_number = null;
    }

    public function updatedType(): void
    {
        $this->weight = match ($this->type) {
            'devoir' => 2.0,
            'interrogation' => 1.0,
            'composition' => 1.0,
            default => 1.0,
        };
    }

    public function save(): void
    {
        $this->authorize('create', GradeSheet::class);

        $isPrimaire = $this->selectedClassroomCycle() === Cycle::Primaire;

        $data = $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'term_id' => $isPrimaire ? ['prohibited'] : ['required', 'exists:terms,id'],
            'composition_number' => $isPrimaire ? ['required', 'integer', 'min:1', 'max:10'] : ['prohibited'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:devoir,interrogation,composition'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'weight' => ['required', 'numeric', 'min:0.5', 'max:20'],
            'graded_on' => ['required', 'date'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $classroom = Classroom::findOrFail($data['classroom_id']);

        $isAssigned = $classroom->level->cycle === Cycle::Secondaire
            ? $user->isAssignedToClassroom($classroom->id, (int) $data['subject_id'])
            : $user->isAssignedToClassroom($classroom->id);

        if (! $this->hasBroadGradeAccess($user) && ! $isAssigned) {
            abort(403, "Vous n'êtes pas affecté à cette classe pour cette matière.");
        }

        if (! $classroom->isGradable()) {
            throw ValidationException::withMessages([
                'classroom_id' => "Ce niveau n'a pas de notation.",
            ]);
        }

        $allowedTypes = $classroom->level->cycle === Cycle::Primaire ? ['composition'] : ['devoir', 'interrogation'];

        if (! in_array($data['type'], $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'type' => "Type d'évaluation invalide pour ce cycle.",
            ]);
        }

        $subject = Subject::findOrFail($data['subject_id']);

        if (! $this->subjectAllowedForCycle($subject, $classroom->level->cycle)) {
            throw ValidationException::withMessages([
                'subject_id' => "Cette matière n'est pas disponible pour ce cycle.",
            ]);
        }

        GradeSheet::create([...$data, 'teacher_id' => $user->id]);

        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    public function selectedClassroomCycle(): ?Cycle
    {
        return $this->classroom_id ? Classroom::find($this->classroom_id)?->level?->cycle : null;
    }

    private function subjectAllowedForCycle(Subject $subject, Cycle $cycle): bool
    {
        return $cycle === Cycle::Primaire ? $subject->is_prescolaire_primaire : $subject->is_secondaire;
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
            ->when(! $isAdmin, fn ($query) => $query->where('teacher_id', $user->id))
            ->orderByDesc('graded_on')
            ->get();

        $assignments = TeacherAssignment::query()
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('classroom.level', fn ($query) => $query->where('cycle', '!=', Cycle::Prescolaire))
            ->with(['classroom', 'subject'])
            ->get();

        if ($isAdmin) {
            $subjects = Subject::orderBy('name')->get();
        } else {
            $assignedSubjects = $assignments->pluck('subject')->filter();

            // Une affectation "classe entière" (préscolaire/primaire, sans matière)
            // autorise l'enseignant à noter n'importe quelle matière de ce cycle.
            $subjects = $assignments->contains(fn (TeacherAssignment $assignment) => $assignment->subject_id === null)
                ? $assignedSubjects->merge(Subject::where('is_prescolaire_primaire', true)->get())->unique('id')->values()
                : $assignedSubjects->unique('id')->values();
        }

        $cycle = $this->selectedClassroomCycle();

        if ($cycle !== null) {
            $subjects = $subjects->filter(fn (Subject $subject) => $this->subjectAllowedForCycle($subject, $cycle))->values();
        }

        return view('livewire.grading.grade-sheets.index', [
            'gradeSheets' => $gradeSheets,
            'classrooms' => $isAdmin ? Classroom::gradable()->orderBy('name')->get() : $assignments->pluck('classroom')->unique('id'),
            'subjects' => $subjects,
            'terms' => Term::orderBy('sequence')->get(),
        ]);
    }
}
