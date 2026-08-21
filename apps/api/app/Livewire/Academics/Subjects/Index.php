<?php

declare(strict_types=1);

namespace App\Livewire\Academics\Subjects;

use App\Domain\Academics\Models\Domain;
use App\Domain\Academics\Models\Subject;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $abbreviation = '';

    public bool $is_prescolaire_primaire = true;

    public bool $is_secondaire = true;

    public ?int $domain_id = null;

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
        $this->abbreviation = $subject->abbreviation;
        $this->is_prescolaire_primaire = $subject->is_prescolaire_primaire;
        $this->is_secondaire = $subject->is_secondaire;
        $this->domain_id = $subject->domain_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:10'],
            'is_prescolaire_primaire' => ['boolean'],
            'is_secondaire' => ['boolean'],
            'domain_id' => ['nullable', 'exists:domains,id'],
        ]);

        if (! $data['is_prescolaire_primaire'] && ! $data['is_secondaire']) {
            throw ValidationException::withMessages([
                'is_prescolaire_primaire' => __('Sélectionnez au moins un cycle pour cette matière.'),
            ]);
        }

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
        $this->reset(['editingId', 'name', 'abbreviation', 'domain_id']);
        $this->is_prescolaire_primaire = true;
        $this->is_secondaire = true;
    }

    public function render()
    {
        return view('livewire.academics.subjects.index', [
            'subjects' => Subject::with('domain')->orderBy('name')->get(),
            'domains' => Domain::orderBy('name')->get(),
        ])->title(__('Matières'));
    }
}
