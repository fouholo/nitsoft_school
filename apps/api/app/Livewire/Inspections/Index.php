<?php

declare(strict_types=1);

namespace App\Livewire\Inspections;

use App\Domain\Establishments\Models\Direction;
use App\Domain\Establishments\Models\Inspection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $codeiep = '';

    public string $inspection_name = '';

    public string $address = '';

    public string $phone_number = '';

    public string $email = '';

    public string $location = '';

    public string $uid_direction = '';

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

    public function edit(int $id): void
    {
        $inspection = Inspection::findOrFail($id);

        $this->authorize('update', $inspection);

        $this->editingId = $inspection->id;
        $this->codeiep = $inspection->codeiep;
        $this->inspection_name = $inspection->inspection_name;
        $this->address = (string) $inspection->address;
        $this->phone_number = (string) $inspection->phone_number;
        $this->email = (string) $inspection->email;
        $this->location = (string) $inspection->location;
        $this->uid_direction = (string) $inspection->uid_direction;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'codeiep' => ['required', 'string', 'max:6', Rule::unique('inspections', 'codeiep')->ignore($this->editingId)],
            'inspection_name' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:50'],
            'location' => ['nullable', 'string', 'max:50'],
            'uid_direction' => ['nullable', 'string', 'exists:directions,uid_serveur'],
        ]);

        foreach (['address', 'phone_number', 'email', 'location', 'uid_direction'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        if ($this->editingId !== null) {
            $inspection = Inspection::findOrFail($this->editingId);
            $this->authorize('update', $inspection);
            $inspection->update($data);
        } else {
            $this->authorize('create', Inspection::class);
            Inspection::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $inspection = Inspection::findOrFail($id);

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
        $this->reset(['editingId', 'codeiep', 'inspection_name', 'address', 'phone_number', 'email', 'location', 'uid_direction']);
    }

    public function render()
    {
        return view('livewire.inspections.index', [
            'inspections' => Inspection::orderBy('inspection_name')->get(),
            'directions' => Direction::orderBy('direction_name')->get(),
        ])->title(__('Inspections'));
    }
}
