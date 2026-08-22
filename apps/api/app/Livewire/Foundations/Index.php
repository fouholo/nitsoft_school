<?php

declare(strict_types=1);

namespace App\Livewire\Foundations;

use App\Domain\Establishments\Models\Foundation;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Foundation::class);
    }

    public function create(): void
    {
        $this->authorize('create', Foundation::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $foundationId): void
    {
        $foundation = Foundation::findOrFail($foundationId);

        $this->authorize('update', $foundation);

        $this->editingId = $foundation->id;
        $this->name = $foundation->name;
        $this->is_active = $foundation->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            $foundation = Foundation::findOrFail($this->editingId);
            $this->authorize('update', $foundation);
        } else {
            $this->authorize('create', Foundation::class);
            $foundation = new Foundation;
            $data['slug'] = $this->uniqueSlugFor($data['name']);
        }

        $foundation->fill($data);
        $foundation->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $foundationId): void
    {
        $foundation = Foundation::findOrFail($foundationId);

        $this->authorize('delete', $foundation);

        $foundation->delete();
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

        while (Foundation::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'is_active']);
        $this->is_active = true;
    }

    public function render()
    {
        return view('livewire.foundations.index', [
            'foundations' => Foundation::withCount('establishments')->orderBy('name')->get(),
        ])->title(__('Groupes scolaires'));
    }
}
