<?php

declare(strict_types=1);

namespace App\Livewire\Academics\PrimarySubjects;

use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Academics\Models\Subject;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $subject_id = null;

    public ?string $coefficient_cp1 = null;

    public ?string $coefficient_cp2 = null;

    public ?string $coefficient_ce1 = null;

    public ?string $coefficient_ce2 = null;

    public ?string $coefficient_cm1 = null;

    public ?string $coefficient_cm2 = null;

    public ?string $bareme_cp1 = null;

    public ?string $bareme_cp2 = null;

    public ?string $bareme_ce1 = null;

    public ?string $bareme_ce2 = null;

    public ?string $bareme_cm1 = null;

    public ?string $bareme_cm2 = null;

    public function mount(): void
    {
        $this->authorize('viewAny', PrimarySubject::class);
    }

    public function create(): void
    {
        $this->authorize('create', PrimarySubject::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $primarySubjectId): void
    {
        $primarySubject = PrimarySubject::findOrFail($primarySubjectId);

        $this->authorize('update', $primarySubject);

        $this->editingId = $primarySubject->id;
        $this->subject_id = $primarySubject->subject_id;
        $this->coefficient_cp1 = $primarySubject->coefficient_cp1;
        $this->coefficient_cp2 = $primarySubject->coefficient_cp2;
        $this->coefficient_ce1 = $primarySubject->coefficient_ce1;
        $this->coefficient_ce2 = $primarySubject->coefficient_ce2;
        $this->coefficient_cm1 = $primarySubject->coefficient_cm1;
        $this->coefficient_cm2 = $primarySubject->coefficient_cm2;
        $this->bareme_cp1 = $primarySubject->bareme_cp1;
        $this->bareme_cp2 = $primarySubject->bareme_cp2;
        $this->bareme_ce1 = $primarySubject->bareme_ce1;
        $this->bareme_ce2 = $primarySubject->bareme_ce2;
        $this->bareme_cm1 = $primarySubject->bareme_cm1;
        $this->bareme_cm2 = $primarySubject->bareme_cm2;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id', Rule::unique('primary_subjects', 'subject_id')->ignore($this->editingId)],
            'coefficient_cp1' => ['nullable', 'numeric', 'min:0.5', 'max:20'],
            'coefficient_cp2' => ['nullable', 'numeric', 'min:0.5', 'max:20'],
            'coefficient_ce1' => ['nullable', 'numeric', 'min:0.5', 'max:20'],
            'coefficient_ce2' => ['nullable', 'numeric', 'min:0.5', 'max:20'],
            'coefficient_cm1' => ['nullable', 'numeric', 'min:0.5', 'max:20'],
            'coefficient_cm2' => ['nullable', 'numeric', 'min:0.5', 'max:20'],
            'bareme_cp1' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'bareme_cp2' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'bareme_ce1' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'bareme_ce2' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'bareme_cm1' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'bareme_cm2' => ['nullable', 'numeric', 'min:1', 'max:1000'],
        ]);

        if ($this->editingId) {
            $primarySubject = PrimarySubject::findOrFail($this->editingId);
            $this->authorize('update', $primarySubject);
        } else {
            $this->authorize('create', PrimarySubject::class);
            $primarySubject = new PrimarySubject;
        }

        $primarySubject->fill($data);
        $primarySubject->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $primarySubjectId): void
    {
        $primarySubject = PrimarySubject::findOrFail($primarySubjectId);

        $this->authorize('delete', $primarySubject);

        $primarySubject->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingId',
            'subject_id',
            'coefficient_cp1',
            'coefficient_cp2',
            'coefficient_ce1',
            'coefficient_ce2',
            'coefficient_cm1',
            'coefficient_cm2',
            'bareme_cp1',
            'bareme_cp2',
            'bareme_ce1',
            'bareme_ce2',
            'bareme_cm1',
            'bareme_cm2',
        ]);
    }

    public function render()
    {
        $usedSubjectIds = PrimarySubject::when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))
            ->pluck('subject_id');

        return view('livewire.academics.primary-subjects.index', [
            'primarySubjects' => PrimarySubject::with('subject')->get()->sortBy('name')->values(),
            'subjects' => Subject::where('is_prescolaire_primaire', true)
                ->whereNotIn('id', $usedSubjectIds)
                ->orderBy('name')
                ->get(),
        ])->title(__('Matières du primaire'));
    }
}
