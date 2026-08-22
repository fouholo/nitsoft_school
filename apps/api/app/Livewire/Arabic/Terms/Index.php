<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Terms;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicTerm;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $label = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public ?int $sequence = null;

    public ?int $school_year_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicTerm::class);
    }

    public function create(): void
    {
        $this->authorize('create', ArabicTerm::class);

        $this->resetForm();
        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->showForm = true;
    }

    public function edit(int $arabicTermId): void
    {
        $arabicTerm = ArabicTerm::findOrFail($arabicTermId);

        $this->authorize('update', $arabicTerm);

        $this->editingId = $arabicTerm->id;
        $this->label = $arabicTerm->label;
        $this->starts_on = $arabicTerm->starts_on?->toDateString() ?? '';
        $this->ends_on = $arabicTerm->ends_on?->toDateString() ?? '';
        $this->sequence = $arabicTerm->sequence;
        $this->school_year_id = $arabicTerm->school_year_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
            'sequence' => ['required', 'integer', 'min:1'],
            'school_year_id' => ['required', 'exists:school_years,id'],
        ]);

        $data['starts_on'] = $data['starts_on'] !== '' ? $data['starts_on'] : null;
        $data['ends_on'] = $data['ends_on'] !== '' ? $data['ends_on'] : null;

        if ($data['starts_on'] !== null && $data['ends_on'] !== null && $data['ends_on'] <= $data['starts_on']) {
            throw ValidationException::withMessages([
                'ends_on' => __('La date de fin doit être postérieure à la date de début.'),
            ]);
        }

        if ($this->editingId) {
            $arabicTerm = ArabicTerm::findOrFail($this->editingId);
            $this->authorize('update', $arabicTerm);
        } else {
            $this->authorize('create', ArabicTerm::class);
            $arabicTerm = new ArabicTerm;
        }

        $arabicTerm->fill($data);
        $arabicTerm->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $arabicTermId): void
    {
        $arabicTerm = ArabicTerm::findOrFail($arabicTermId);

        $this->authorize('delete', $arabicTerm);

        $arabicTerm->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'label', 'starts_on', 'ends_on', 'sequence', 'school_year_id']);
    }

    public function render()
    {
        return view('livewire.arabic.terms.index', [
            'arabicTerms' => ArabicTerm::with('schoolYear')->orderBy('sequence')->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
        ])->title(__('Périodes arabes'));
    }
}
