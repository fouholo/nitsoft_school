<?php

declare(strict_types=1);

namespace App\Livewire\Academics\Levels;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Level;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $level = '';

    public string $level_wording = '';

    public string $cycle = '';

    public bool $requires_series = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Level::class);
    }

    public function create(): void
    {
        $this->authorize('create', Level::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $levelId): void
    {
        $level = Level::findOrFail($levelId);

        $this->authorize('update', $level);

        $this->editingId = $level->id;
        $this->level = $level->level;
        $this->level_wording = $level->level_wording;
        $this->cycle = $level->cycle->value;
        $this->requires_series = $level->requires_series;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'level' => ['required', 'string', 'max:10', Rule::unique('levels', 'level')->ignore($this->editingId)],
            'level_wording' => ['required', 'string', 'max:50'],
            'cycle' => ['required', Rule::enum(Cycle::class)],
            'requires_series' => ['boolean'],
        ]);

        if ($this->editingId) {
            $level = Level::findOrFail($this->editingId);
            $this->authorize('update', $level);
        } else {
            $this->authorize('create', Level::class);
            $level = new Level;
        }

        $level->fill($data);
        $level->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $levelId): void
    {
        $level = Level::findOrFail($levelId);

        $this->authorize('delete', $level);

        $level->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'level', 'level_wording', 'cycle']);
        $this->requires_series = false;
    }

    public function render()
    {
        return view('livewire.academics.levels.index', [
            'levels' => Level::orderBy('level_wording')->get(),
            'cycles' => Cycle::cases(),
        ])->title(__('Niveaux'));
    }
}
