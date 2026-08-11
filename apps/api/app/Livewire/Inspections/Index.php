<?php

declare(strict_types=1);

namespace App\Livewire\Inspections;

use App\Domain\Establishments\Models\Inspection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Inspections')]
class Index extends Component
{
    public bool $showForm = false;

    public ?string $editingCode = null;

    public string $code = '';

    public string $libelle = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Inspection::class);
    }

    public function create(): void
    {
        $this->authorize('create', Inspection::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $code): void
    {
        $inspection = Inspection::findOrFail($code);

        $this->authorize('update', $inspection);

        $this->editingCode = $inspection->code;
        $this->code = $inspection->code;
        $this->libelle = $inspection->libelle;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('inspections', 'code')->ignore($this->editingCode, 'code')],
            'libelle' => ['required', 'string', 'max:100'],
        ]);

        if ($this->editingCode !== null) {
            $inspection = Inspection::findOrFail($this->editingCode);
            $this->authorize('update', $inspection);
            $inspection->update(['libelle' => $data['libelle']]);
        } else {
            $this->authorize('create', Inspection::class);
            Inspection::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(string $code): void
    {
        $inspection = Inspection::findOrFail($code);

        $this->authorize('delete', $inspection);

        $inspection->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingCode', 'code', 'libelle']);
    }

    public function render()
    {
        return view('livewire.inspections.index', [
            'inspections' => Inspection::orderBy('libelle')->get(),
        ]);
    }
}
