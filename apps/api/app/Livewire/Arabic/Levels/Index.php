<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Levels;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Arabic\Models\ArabicLevel;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $wording = '';

    public string $cycle = '';

    public bool $requires_series = false;

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicLevel::class);
    }

    public function create(): void
    {
        $this->authorize('create', ArabicLevel::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $arabicLevelId): void
    {
        $arabicLevel = ArabicLevel::findOrFail($arabicLevelId);

        $this->authorize('update', $arabicLevel);

        $this->editingId = $arabicLevel->id;
        $this->code = $arabicLevel->code;
        $this->wording = $arabicLevel->wording;
        $this->cycle = $arabicLevel->cycle->value;
        $this->requires_series = $arabicLevel->requires_series;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'wording' => ['required', 'string', 'max:100'],
            'cycle' => ['required', Rule::enum(Cycle::class)],
            'requires_series' => ['boolean'],
        ]);

        if ($this->editingId) {
            $arabicLevel = ArabicLevel::findOrFail($this->editingId);
            $this->authorize('update', $arabicLevel);
        } else {
            $this->authorize('create', ArabicLevel::class);
            $arabicLevel = new ArabicLevel;
        }

        $arabicLevel->fill($data);
        $arabicLevel->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $arabicLevelId): void
    {
        $arabicLevel = ArabicLevel::findOrFail($arabicLevelId);

        $this->authorize('delete', $arabicLevel);

        $arabicLevel->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'wording', 'cycle']);
        $this->requires_series = false;
    }

    public function render()
    {
        return view('livewire.arabic.levels.index', [
            'arabicLevels' => ArabicLevel::orderBy('wording')->get(),
            'cycles' => Cycle::cases(),
        ])->title(__('Niveaux arabes'));
    }
}
