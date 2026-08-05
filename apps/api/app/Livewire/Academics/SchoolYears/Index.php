<?php

declare(strict_types=1);

namespace App\Livewire\Academics\SchoolYears;

use App\Domain\Academics\Models\SchoolYear;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Années scolaires')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $label = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public bool $is_current = false;

    public function mount(): void
    {
        $this->authorize('viewAny', SchoolYear::class);
    }

    public function create(): void
    {
        $this->authorize('create', SchoolYear::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $schoolYearId): void
    {
        $schoolYear = SchoolYear::findOrFail($schoolYearId);

        $this->authorize('update', $schoolYear);

        $this->editingId = $schoolYear->id;
        $this->label = $schoolYear->label;
        $this->starts_on = $schoolYear->starts_on->toDateString();
        $this->ends_on = $schoolYear->ends_on->toDateString();
        $this->is_current = $schoolYear->is_current;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_current' => ['boolean'],
        ]);

        if ($this->editingId) {
            $schoolYear = SchoolYear::findOrFail($this->editingId);
            $this->authorize('update', $schoolYear);
        } else {
            $this->authorize('create', SchoolYear::class);
            $schoolYear = new SchoolYear;
        }

        if ($data['is_current']) {
            SchoolYear::query()->where('is_current', true)->update(['is_current' => false]);
        }

        $schoolYear->fill($data);
        $schoolYear->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $schoolYearId): void
    {
        $schoolYear = SchoolYear::findOrFail($schoolYearId);

        $this->authorize('delete', $schoolYear);

        $schoolYear->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'label', 'starts_on', 'ends_on', 'is_current']);
    }

    public function render()
    {
        return view('livewire.academics.school-years.index', [
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
        ]);
    }
}
