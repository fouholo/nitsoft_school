<?php

declare(strict_types=1);

namespace App\Livewire\Grading\GradeSheets\Primaire;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Academics\Models\TeacherAssignment;
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

    public ?int $primary_subject_id = null;

    public ?int $composition_number = null;

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

        $this->reset(['classroom_id', 'primary_subject_id', 'composition_number', 'title']);
        $this->max_score = 20.0;
        $this->weight = 1.0;
        $this->graded_on = now()->toDateString();
        $this->showForm = true;
    }

    public function updatedClassroomId(): void
    {
        $this->weight = 1.0;
        $this->primary_subject_id = null;
        $this->composition_number = null;
    }

    public function save(): void
    {
        $this->authorize('create', GradeSheet::class);

        $data = $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'primary_subject_id' => ['required', 'exists:primary_subjects,id'],
            'composition_number' => ['required', 'integer', 'min:1', 'max:10'],
            'title' => ['required', 'string', 'max:255'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'weight' => ['required', 'numeric', 'min:0.5', 'max:20'],
            'graded_on' => ['required', 'date'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $classroom = Classroom::findOrFail($data['classroom_id']);

        if ($classroom->level->cycle !== Cycle::Primaire) {
            throw ValidationException::withMessages([
                'classroom_id' => "Cette classe n'est pas une classe primaire.",
            ]);
        }

        if (! $this->hasBroadGradeAccess($user) && ! $user->isAssignedToClassroom($classroom->id)) {
            abort(403, "Vous n'êtes pas affecté à cette classe.");
        }

        if (! $classroom->isGradable()) {
            throw ValidationException::withMessages([
                'classroom_id' => "Ce niveau n'a pas de notation.",
            ]);
        }

        $primarySubject = PrimarySubject::findOrFail($data['primary_subject_id']);

        if ($primarySubject->coefficientFor($classroom->level) === null) {
            throw ValidationException::withMessages([
                'primary_subject_id' => "Cette matière n'est pas configurée pour ce niveau.",
            ]);
        }

        GradeSheet::create([...$data, 'type' => 'composition', 'teacher_id' => $user->id]);

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
            ->with(['classroom', 'primarySubject'])
            ->whereHas('classroom.level', fn ($query) => $query->where('cycle', Cycle::Primaire))
            ->when(! $isAdmin, fn ($query) => $query->where('teacher_id', $user->id))
            ->orderByDesc('graded_on')
            ->get();

        $assignments = TeacherAssignment::query()
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('classroom.level', fn ($query) => $query->where('cycle', Cycle::Primaire))
            ->with('classroom')
            ->get();

        $classroom = $this->classroom_id ? Classroom::find($this->classroom_id) : null;

        // Une affectation primaire porte toujours sur la classe entière (pas
        // de matière) : si l'enseignant a une affectation sur cette classe,
        // il peut noter n'importe quelle matière configurée pour ce niveau.
        $isAssignedToClassroom = $classroom && ($isAdmin || $assignments->contains(fn (TeacherAssignment $a) => $a->classroom_id === $classroom->id));

        $subjects = $isAssignedToClassroom
            ? PrimarySubject::whereNotNull(PrimarySubject::coefficientColumn($classroom->level))->orderBy('name')->get()
            : collect();

        return view('livewire.grading.grade-sheets.primaire.index', [
            'gradeSheets' => $gradeSheets,
            'classrooms' => $isAdmin
                ? Classroom::gradable()->whereHas('level', fn ($query) => $query->where('cycle', Cycle::Primaire))->orderBy('name')->get()
                : $assignments->pluck('classroom')->unique('id'),
            'subjects' => $subjects,
        ]);
    }
}
