<?php

declare(strict_types=1);

namespace App\Livewire\Grading\GradeSheets\Primaire;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
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

    public ?int $composition_number = null;

    public string $title = '';

    public string $graded_on = '';

    public function mount(): void
    {
        $this->authorize('viewAny', GradeSheet::class);
    }

    public function create(): void
    {
        $this->authorize('create', GradeSheet::class);

        $this->reset(['classroom_id', 'composition_number', 'title']);
        $this->graded_on = now()->toDateString();
        $this->showForm = true;
    }

    public function updatedClassroomId(): void
    {
        $this->composition_number = null;
    }

    public function save(): void
    {
        $this->authorize('create', GradeSheet::class);

        $data = $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'composition_number' => ['required', 'integer', 'min:1', 'max:10'],
            'title' => ['required', 'string', 'max:255'],
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

        GradeSheet::create([...$data, 'type' => 'composition', 'teacher_id' => $user->id]);

        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    /**
     * Accès large aux évaluations (toutes classes, pas seulement ses propres
     * affectations) : admin classique (vue seule depuis ce chantier) ou
     * educateur (vue + saisie) — voir RolePermissions::MATRIX['grades.enter'].
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
            ->with('classroom')
            ->whereHas('classroom.level', fn ($query) => $query->where('cycle', Cycle::Primaire))
            ->when(! $isAdmin, fn ($query) => $query->where('teacher_id', $user->id))
            ->orderByDesc('graded_on')
            ->get();

        $assignments = TeacherAssignment::query()
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('classroom.level', fn ($query) => $query->where('cycle', Cycle::Primaire))
            ->with('classroom')
            ->get();

        return view('livewire.grading.grade-sheets.primaire.index', [
            'gradeSheets' => $gradeSheets,
            'classrooms' => $isAdmin
                ? Classroom::gradable()->whereHas('level', fn ($query) => $query->where('cycle', Cycle::Primaire))->orderBy('name')->get()
                : $assignments->pluck('classroom')->unique('id'),
        ]);
    }
}
