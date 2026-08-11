<?php

declare(strict_types=1);

namespace App\Livewire\Establishments;

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Établissements')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $foundation_id = null;

    public string $type = '';

    public string $address = '';

    public string $phone = '';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Establishment::class);
    }

    public function create(): void
    {
        $this->authorize('create', Establishment::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $establishmentId): void
    {
        $establishment = Establishment::findOrFail($establishmentId);

        $this->authorize('update', $establishment);

        $this->editingId = $establishment->id;
        $this->name = $establishment->name;
        $this->foundation_id = $establishment->foundation_id;
        $this->type = $establishment->type instanceof EstablishmentType ? $establishment->type->value : '';
        $this->address = (string) $establishment->address;
        $this->phone = (string) $establishment->phone;
        $this->is_active = $establishment->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'foundation_id' => ['nullable', 'integer', 'exists:foundations,id'],
            'type' => ['required', Rule::enum(EstablishmentType::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            $establishment = Establishment::findOrFail($this->editingId);
            $this->authorize('update', $establishment);
        } else {
            $this->authorize('create', Establishment::class);
            $establishment = new Establishment;
            $data['slug'] = $this->uniqueSlugFor($data['name']);
        }

        $establishment->fill($data);
        $establishment->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $establishmentId): void
    {
        $establishment = Establishment::findOrFail($establishmentId);

        $this->authorize('delete', $establishment);

        $establishment->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Establishment::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'foundation_id', 'type', 'address', 'phone', 'is_active']);
        $this->is_active = true;
    }

    public function render()
    {
        return view('livewire.establishments.index', [
            'establishments' => Establishment::with('foundation')->orderBy('name')->get(),
            'foundations' => Foundation::orderBy('name')->get(),
            'types' => EstablishmentType::cases(),
        ]);
    }
}
