<?php

declare(strict_types=1);

namespace App\Livewire\Academics\Classrooms;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Serie;
use App\Domain\Establishments\Models\Establishment;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Classes')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $cycle = Cycle::Secondaire->value;

    public ?int $level_id = null;

    public ?int $serie_id = null;

    public string $numero = '';

    public ?int $capacity = null;

    public ?int $school_year_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Classroom::class);

        $this->cycle = $this->currentEstablishment()->allowedCycles()[0]->value;
    }

    public function updatedCycle(): void
    {
        $this->reset(['level_id', 'serie_id', 'numero']);
    }

    public function updatedLevelId(): void
    {
        $this->reset(['serie_id', 'numero']);
    }

    public function create(): void
    {
        $this->authorize('create', Classroom::class);

        $this->resetForm();
        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->showForm = true;
    }

    public function edit(int $classroomId): void
    {
        $classroom = Classroom::findOrFail($classroomId);

        $this->authorize('update', $classroom);

        $this->editingId = $classroom->id;
        $this->cycle = $classroom->level->cycle->value;
        $this->level_id = $classroom->level_id;
        $this->serie_id = $classroom->serie_id;
        $this->numero = $classroom->numero;
        $this->capacity = $classroom->capacity;
        $this->school_year_id = $classroom->school_year_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'level_id' => ['required', 'exists:levels,id'],
            'serie_id' => [Rule::requiredIf(fn () => $this->selectedLevelRequiresSeries()), 'nullable', 'exists:series,id'],
            'numero' => ['nullable', 'string', 'max:2'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'school_year_id' => ['required', 'exists:school_years,id'],
        ]);

        $level = Level::findOrFail($data['level_id']);

        if (! in_array($level->cycle, $this->currentEstablishment()->allowedCycles(), true)) {
            throw ValidationException::withMessages([
                'level_id' => "Ce niveau n'est pas disponible pour ce type d'établissement.",
            ]);
        }

        if (! $this->selectedLevelRequiresSeries()) {
            $data['serie_id'] = null;
        }

        if ($this->editingId) {
            $classroom = Classroom::findOrFail($this->editingId);
            $this->authorize('update', $classroom);
        } else {
            $this->authorize('create', Classroom::class);
            $classroom = new Classroom;
        }

        $classroom->fill($data);
        $classroom->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $classroomId): void
    {
        $classroom = Classroom::findOrFail($classroomId);

        $this->authorize('delete', $classroom);

        $classroom->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'level_id', 'serie_id', 'numero', 'capacity', 'school_year_id']);
        $this->cycle = $this->currentEstablishment()->allowedCycles()[0]->value;
    }

    private function currentEstablishment(): Establishment
    {
        return Establishment::findOrFail((int) app('currentEstablishmentId'));
    }

    public function selectedLevelRequiresSeries(): bool
    {
        if (! $this->level_id) {
            return false;
        }

        return (bool) Level::whereKey($this->level_id)->value('requires_series');
    }

    /**
     * @return array<int, string>
     */
    public function numeroOptions(): array
    {
        return in_array($this->cycle, [Cycle::Prescolaire->value, Cycle::Primaire->value], true)
            ? ['A', 'B', 'C', 'D', 'E', 'F']
            : array_map('strval', range(1, 10));
    }

    public function render()
    {
        return view('livewire.academics.classrooms.index', [
            'classrooms' => Classroom::with(['schoolYear', 'level', 'serie'])->orderBy('name')->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'cycles' => $this->currentEstablishment()->allowedCycles(),
            'levels' => $this->cycle !== ''
                ? Level::where('cycle', $this->cycle)->orderBy('level_wording')->get()
                : collect(),
            'series' => Serie::orderBy('serie')->get(),
        ]);
    }
}
