<?php

declare(strict_types=1);

namespace App\Livewire\Domains;

use App\Domain\Academics\Models\Domain;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Domaines')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Domain::class);
    }

    public function create(): void
    {
        $this->authorize('create', Domain::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $domainId): void
    {
        $domain = Domain::findOrFail($domainId);

        $this->authorize('update', $domain);

        $this->editingId = $domain->id;
        $this->name = $domain->name;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('domains', 'name')->ignore($this->editingId)],
        ]);

        if ($this->editingId) {
            $domain = Domain::findOrFail($this->editingId);
            $this->authorize('update', $domain);
            $domain->update($data);
        } else {
            $this->authorize('create', Domain::class);
            Domain::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $domainId): void
    {
        $domain = Domain::findOrFail($domainId);

        $this->authorize('delete', $domain);

        $domain->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'name']);
    }

    public function render()
    {
        return view('livewire.domains.index', [
            'domains' => Domain::orderBy('name')->get(),
        ]);
    }
}
