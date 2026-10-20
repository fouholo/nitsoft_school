<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Classrooms;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Classes de la filière arabe, miroir de Academics\Classrooms\Index — voir
 * docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $arabic_level_id = null;

    public ?int $arabic_serie_id = null;

    public string $numero = '';

    public ?int $capacity = null;

    public ?int $school_year_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicClassroom::class);
    }

    public function updatedArabicLevelId(): void
    {
        $this->reset(['arabic_serie_id', 'numero']);
    }

    public function create(): void
    {
        $this->authorize('create', ArabicClassroom::class);

        $this->resetForm();
        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->showForm = true;
    }

    public function edit(int $arabicClassroomId): void
    {
        $arabicClassroom = ArabicClassroom::findOrFail($arabicClassroomId);

        $this->authorize('update', $arabicClassroom);

        $this->editingId = $arabicClassroom->id;
        $this->arabic_level_id = $arabicClassroom->arabic_level_id;
        $this->arabic_serie_id = $arabicClassroom->arabic_serie_id;
        $this->numero = (string) $arabicClassroom->numero;
        $this->capacity = $arabicClassroom->capacity;
        $this->school_year_id = $arabicClassroom->school_year_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'arabic_level_id' => ['required', 'exists:arabic_levels,id'],
            'arabic_serie_id' => [Rule::requiredIf(fn () => $this->selectedLevelRequiresSeries()), 'nullable', 'exists:arabic_series,id'],
            'numero' => ['nullable', 'string', 'max:2'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'school_year_id' => ['required', 'exists:school_years,id'],
        ], [], [
            'arabic_level_id' => __('niveau arabe'),
            'arabic_serie_id' => __('série arabe'),
            'numero' => __('numéro'),
            'capacity' => __('capacité'),
            'school_year_id' => __('année scolaire'),
        ]);

        $level = ArabicLevel::findOrFail($data['arabic_level_id']);

        if (! in_array($level->cycle, $this->currentEstablishment()->allowedCycles(), true)) {
            throw ValidationException::withMessages([
                'arabic_level_id' => __("Ce niveau n'est pas disponible pour ce type d'établissement."),
            ]);
        }

        if (! $level->requires_series) {
            $data['arabic_serie_id'] = null;
        }

        $data['numero'] = $data['numero'] !== '' ? $data['numero'] : null;

        $duplicate = ArabicClassroom::query()
            ->where('school_year_id', $data['school_year_id'])
            ->where('arabic_level_id', $data['arabic_level_id'])
            ->where('arabic_serie_id', $data['arabic_serie_id'])
            ->where('numero', $data['numero'])
            ->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'numero' => __('Cette classe arabe existe déjà pour cette année scolaire.'),
            ]);
        }

        if ($this->editingId) {
            $arabicClassroom = ArabicClassroom::findOrFail($this->editingId);
            $this->authorize('update', $arabicClassroom);
        } else {
            $this->authorize('create', ArabicClassroom::class);
            $arabicClassroom = new ArabicClassroom;
        }

        $arabicClassroom->fill($data);
        $arabicClassroom->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $arabicClassroomId): void
    {
        $arabicClassroom = ArabicClassroom::findOrFail($arabicClassroomId);

        $this->authorize('delete', $arabicClassroom);

        // Inscriptions retirées ou supprimées en douceur comprises : la clé
        // étrangère (restrictOnDelete) bloquerait de toute façon la suppression.
        if ($arabicClassroom->enrollments()->withoutGlobalScope(SoftDeletingScope::class)->exists()) {
            throw ValidationException::withMessages([
                'delete' => __('Impossible de supprimer une classe qui a des élèves inscrits.'),
            ]);
        }

        $arabicClassroom->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->resetErrorBag();
        $this->reset(['editingId', 'arabic_level_id', 'arabic_serie_id', 'numero', 'capacity', 'school_year_id']);
    }

    private function currentEstablishment(): Establishment
    {
        return Establishment::findOrFail((int) app('currentEstablishmentId'));
    }

    public function selectedLevelRequiresSeries(): bool
    {
        return $this->arabic_level_id
            ? (bool) ArabicLevel::whereKey($this->arabic_level_id)->value('requires_series')
            : false;
    }

    /**
     * @return array<int, string>
     */
    public function numeroOptions(): array
    {
        $cycle = $this->arabic_level_id ? ArabicLevel::find($this->arabic_level_id)?->cycle : null;

        return in_array($cycle, [Cycle::Prescolaire, Cycle::Primaire], true)
            ? ['A', 'B', 'C', 'D', 'E', 'F']
            : array_map('strval', range(1, 10));
    }

    public function render()
    {
        $allowedCycles = array_map(fn (Cycle $cycle) => $cycle->value, $this->currentEstablishment()->allowedCycles());

        return view('livewire.arabic.classrooms.index', [
            'arabicClassrooms' => ArabicClassroom::with(['schoolYear', 'arabicLevel', 'arabicSerie'])
                ->withCount(['enrollments' => fn ($query) => $query->where('status', 'active')])
                ->orderBy('name')
                ->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'arabicLevels' => ArabicLevel::whereIn('cycle', $allowedCycles)->orderBy('wording')->get(),
            'arabicSeries' => ArabicSerie::orderBy('serie')->get(),
        ])->title(__('Classes arabes'));
    }
}
