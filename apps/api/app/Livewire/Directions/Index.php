<?php

declare(strict_types=1);

namespace App\Livewire\Directions;

use App\Domain\Establishments\Models\Direction;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Directions')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $direction_name = '';

    public string $address = '';

    public string $phone_number = '';

    public string $email = '';

    public string $location = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Direction::class);
    }

    public function create(): void
    {
        $this->authorize('create', Direction::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $direction = Direction::findOrFail($id);

        $this->authorize('update', $direction);

        $this->editingId = $direction->id;
        $this->code = $direction->code;
        $this->direction_name = $direction->direction_name;
        $this->address = (string) $direction->address;
        $this->phone_number = (string) $direction->phone_number;
        $this->email = (string) $direction->email;
        $this->location = (string) $direction->location;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:6', Rule::unique('directions', 'code')->ignore($this->editingId)],
            'direction_name' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:50'],
            'location' => ['nullable', 'string', 'max:50'],
        ]);

        foreach (['address', 'phone_number', 'email', 'location'] as $field) {
            $data[$field] = $data[$field] !== '' ? $data[$field] : null;
        }

        if ($this->editingId !== null) {
            $direction = Direction::findOrFail($this->editingId);
            $this->authorize('update', $direction);
            $direction->update($data);
        } else {
            $this->authorize('create', Direction::class);
            Direction::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        $direction = Direction::findOrFail($id);

        $this->authorize('delete', $direction);

        $direction->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'direction_name', 'address', 'phone_number', 'email', 'location']);
    }

    public function render()
    {
        return view('livewire.directions.index', [
            'directions' => Direction::orderBy('direction_name')->get(),
        ]);
    }
}
