<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Subjects;

use App\Domain\Arabic\Models\ArabicSubject;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Matières arabes')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $abbreviation = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicSubject::class);
    }

    public function create(): void
    {
        $this->authorize('create', ArabicSubject::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $arabicSubjectId): void
    {
        $arabicSubject = ArabicSubject::findOrFail($arabicSubjectId);

        $this->authorize('update', $arabicSubject);

        $this->editingId = $arabicSubject->id;
        $this->name = $arabicSubject->name;
        $this->abbreviation = (string) $arabicSubject->abbreviation;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
        ]);

        if ($this->editingId) {
            $arabicSubject = ArabicSubject::findOrFail($this->editingId);
            $this->authorize('update', $arabicSubject);
        } else {
            $this->authorize('create', ArabicSubject::class);
            $arabicSubject = new ArabicSubject;
        }

        $arabicSubject->fill($data);
        $arabicSubject->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $arabicSubjectId): void
    {
        $arabicSubject = ArabicSubject::findOrFail($arabicSubjectId);

        $this->authorize('delete', $arabicSubject);

        $arabicSubject->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'abbreviation']);
    }

    public function render()
    {
        return view('livewire.arabic.subjects.index', [
            'arabicSubjects' => ArabicSubject::orderBy('name')->get(),
        ]);
    }
}
