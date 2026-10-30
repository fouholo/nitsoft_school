<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Compositions;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Enums\SchoolMonth;
use App\Domain\Arabic\Models\ArabicComposition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Compositions mensuelles du primaire arabe de l'année courante — voir
 * docs/superpowers/specs/2026-10-30-compositions-primaire-arabe-design.md.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $month = null;

    public string $graded_on = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicComposition::class);
    }

    public function create(): void
    {
        $this->authorize('create', ArabicComposition::class);

        $this->resetErrorBag();
        $this->month = (int) now()->format('n');
        $this->graded_on = now()->toDateString();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('create', ArabicComposition::class);

        $schoolYearId = $this->currentSchoolYearId();

        if ($schoolYearId === null) {
            throw ValidationException::withMessages(['month' => __('Aucune année scolaire courante.')]);
        }

        $data = $this->validate([
            'month' => [
                'required',
                Rule::enum(SchoolMonth::class),
                Rule::unique('arabic_compositions', 'month')
                    ->where('establishment_id', (int) app('currentEstablishmentId'))
                    ->where('school_year_id', $schoolYearId),
            ],
            'graded_on' => ['required', 'date'],
        ], [
            'month.unique' => __('Une composition existe déjà pour ce mois.'),
        ], [
            'month' => __('mois'),
            'graded_on' => __('date'),
        ]);

        ArabicComposition::create([
            ...$data,
            'establishment_id' => (int) app('currentEstablishmentId'),
            'school_year_id' => $schoolYearId,
            'created_by' => Auth::id(),
        ]);

        $this->showForm = false;
    }

    public function delete(int $arabicCompositionId): void
    {
        $arabicComposition = ArabicComposition::findOrFail($arabicCompositionId);

        $this->authorize('delete', $arabicComposition);

        if ($arabicComposition->grades()->exists()) {
            throw ValidationException::withMessages([
                'delete' => __('Impossible de supprimer une composition qui a déjà des notes.'),
            ]);
        }

        $arabicComposition->delete();
    }

    public function cancel(): void
    {
        $this->resetErrorBag();
        $this->showForm = false;
    }

    private function currentSchoolYearId(): ?int
    {
        return SchoolYear::where('is_current', true)->value('id');
    }

    public function render()
    {
        return view('livewire.arabic.compositions.index', [
            'arabicCompositions' => ArabicComposition::query()
                ->where('school_year_id', $this->currentSchoolYearId())
                ->withCount('grades')
                ->get()
                ->sortBy(fn (ArabicComposition $composition) => $composition->month->position())
                ->values(),
            'months' => SchoolMonth::cases(),
        ])->title(__('Compositions'));
    }
}
