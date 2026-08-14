<?php

declare(strict_types=1);

namespace App\Livewire\Academics\Terms;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Term;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Périodes')]
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
        $this->authorize('viewAny', Term::class);
    }

    public function create(): void
    {
        $this->authorize('create', Term::class);

        $this->resetForm();
        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->showForm = true;
    }

    public function edit(int $termId): void
    {
        $term = Term::findOrFail($termId);

        $this->authorize('update', $term);

        $this->editingId = $term->id;
        $this->label = $term->label;
        $this->starts_on = $term->starts_on->toDateString();
        $this->ends_on = $term->ends_on->toDateString();
        $this->sequence = $term->sequence;
        $this->school_year_id = $term->school_year_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'sequence' => ['required', 'integer', 'min:1'],
            'school_year_id' => ['required', 'exists:school_years,id'],
        ]);

        if ($this->editingId) {
            $term = Term::findOrFail($this->editingId);
            $this->authorize('update', $term);
        } else {
            $this->authorize('create', Term::class);
            $term = new Term;
        }

        $term->fill($data);
        $term->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $termId): void
    {
        $term = Term::findOrFail($termId);

        $this->authorize('delete', $term);

        $term->delete();
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
        return view('livewire.academics.terms.index', [
            'terms' => Term::with('schoolYear')->orderBy('sequence')->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'labelPlaceholder' => $this->labelPlaceholder(),
        ]);
    }

    private function labelPlaceholder(): string
    {
        $establishment = Establishment::findOrFail((int) app('currentEstablishmentId'));

        return $establishment->type === EstablishmentType::PrescolairePrimaire ? 'Composition 1' : 'Trimestre 1';
    }
}
