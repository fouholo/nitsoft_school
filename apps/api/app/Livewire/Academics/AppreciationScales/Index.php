<?php

declare(strict_types=1);

namespace App\Livewire\Academics\AppreciationScales;

use App\Domain\Grading\Models\AppreciationScale;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?string $percentage = null;

    public string $appreciation = '';

    public bool $tableau_honneur = false;

    public bool $tableau_excellence = false;

    public bool $felicitation = false;

    public bool $encouragement = false;

    public function mount(): void
    {
        $this->authorize('viewAny', AppreciationScale::class);
    }

    public function create(): void
    {
        $this->authorize('create', AppreciationScale::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $appreciationScaleId): void
    {
        $appreciationScale = AppreciationScale::findOrFail($appreciationScaleId);

        $this->authorize('update', $appreciationScale);

        $this->editingId = $appreciationScale->id;
        $this->percentage = (string) $appreciationScale->percentage;
        $this->appreciation = $appreciationScale->appreciation;
        $this->tableau_honneur = $appreciationScale->tableau_honneur;
        $this->tableau_excellence = $appreciationScale->tableau_excellence;
        $this->felicitation = $appreciationScale->felicitation;
        $this->encouragement = $appreciationScale->encouragement;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'percentage' => [
                'required',
                'integer',
                'min:0',
                'max:100',
                Rule::unique('appreciation_scales', 'percentage')->ignore($this->editingId),
            ],
            'appreciation' => ['required', 'string', 'max:100'],
            'tableau_honneur' => ['boolean'],
            'tableau_excellence' => ['boolean'],
            'felicitation' => ['boolean'],
            'encouragement' => ['boolean'],
        ]);

        if ($this->editingId) {
            $appreciationScale = AppreciationScale::findOrFail($this->editingId);
            $this->authorize('update', $appreciationScale);
        } else {
            $this->authorize('create', AppreciationScale::class);
            $appreciationScale = new AppreciationScale;
        }

        $appreciationScale->fill($data);
        $appreciationScale->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $appreciationScaleId): void
    {
        $appreciationScale = AppreciationScale::findOrFail($appreciationScaleId);

        $this->authorize('delete', $appreciationScale);

        $appreciationScale->delete();
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
            'percentage',
            'appreciation',
            'tableau_honneur',
            'tableau_excellence',
            'felicitation',
            'encouragement',
        ]);
    }

    public function render()
    {
        return view('livewire.academics.appreciation-scales.index', [
            'appreciationScales' => AppreciationScale::orderByDesc('percentage')->get(),
        ])->title(__("Barème d'appréciations"));
    }
}
