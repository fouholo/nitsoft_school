<?php

declare(strict_types=1);

namespace App\Livewire\Academics\Classrooms;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Classes')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $level = '';

    public string $cycle = Cycle::Secondaire->value;

    public ?int $capacity = null;

    public ?int $school_year_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Classroom::class);
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
        $this->name = $classroom->name;
        $this->level = (string) $classroom->level;
        $this->cycle = $classroom->cycle->value;
        $this->capacity = $classroom->capacity;
        $this->school_year_id = $classroom->school_year_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:255'],
            'cycle' => ['required', Rule::enum(Cycle::class)],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'school_year_id' => ['required', 'exists:school_years,id'],
        ]);

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
        $this->reset(['editingId', 'name', 'level', 'cycle', 'capacity', 'school_year_id']);
        $this->cycle = Cycle::Secondaire->value;
    }

    public function render()
    {
        return view('livewire.academics.classrooms.index', [
            'classrooms' => Classroom::with('schoolYear')->orderBy('name')->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'cycles' => Cycle::cases(),
        ]);
    }
}
