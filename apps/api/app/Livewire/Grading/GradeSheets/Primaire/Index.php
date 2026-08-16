<?php

declare(strict_types=1);

namespace App\Livewire\Grading\GradeSheets\Primaire;

use App\Domain\Grading\Models\GradeSheet;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public bool $showForm = false;

    public ?int $composition_number = null;

    public string $title = '';

    public string $graded_on = '';

    public function mount(): void
    {
        $this->authorize('viewAny', GradeSheet::class);
    }

    public function create(): void
    {
        abort_unless($this->isDirecteur(), 403);

        $this->reset(['composition_number', 'title']);
        $this->graded_on = now()->toDateString();
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless($this->isDirecteur(), 403);

        $data = $this->validate([
            'composition_number' => ['required', 'integer', 'min:1', 'max:10'],
            'title' => ['required', 'string', 'max:255'],
            'graded_on' => ['required', 'date'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        GradeSheet::create([...$data, 'classroom_id' => null, 'type' => 'composition', 'teacher_id' => $user->id]);

        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    /**
     * La création d'une composition primaire est réservée au directeur : elle
     * n'est plus rattachée à une classe/affectation précise (une composition
     * vaut pour toute l'école primaire), la policy générale GradeSheetPolicy
     * (partagée avec le secondaire) ne s'applique donc pas ici.
     */
    private function isDirecteur(): bool
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->currentRole() === 'directeur';
    }

    public function render()
    {
        $gradeSheets = GradeSheet::query()
            ->where('type', 'composition')
            ->orderByDesc('graded_on')
            ->get();

        return view('livewire.grading.grade-sheets.primaire.index', [
            'gradeSheets' => $gradeSheets,
            'isDirecteur' => $this->isDirecteur(),
        ]);
    }
}
