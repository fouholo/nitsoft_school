<?php

declare(strict_types=1);

namespace App\Livewire\Academics\Subjects;

use App\Domain\Academics\Models\Subject;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Matières')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public float $coefficient_default = 1.0;

    public function mount(): void
    {
        $this->authorize('viewAny', Subject::class);
    }

    public function create(): void
    {
        $this->authorize('create', Subject::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $subjectId): void
    {
        $subject = Subject::findOrFail($subjectId);

        $this->authorize('update', $subject);

        $this->editingId = $subject->id;
        $this->name = $subject->name;
        $this->coefficient_default = (float) $subject->coefficient_default;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'coefficient_default' => ['required', 'numeric', 'min:0.5', 'max:20'],
        ]);

        if ($this->editingId) {
            $subject = Subject::findOrFail($this->editingId);
            $this->authorize('update', $subject);
        } else {
            $this->authorize('create', Subject::class);
            $subject = new Subject;
        }

        $subject->fill($data);
        $subject->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $subjectId): void
    {
        $subject = Subject::findOrFail($subjectId);

        $this->authorize('delete', $subject);

        $subject->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name']);
        $this->coefficient_default = 1.0;
    }

    public function render()
    {
        return view('livewire.academics.subjects.index', [
            'subjects' => Subject::orderBy('name')->get(),
        ]);
    }
}
