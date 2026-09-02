<?php

declare(strict_types=1);

namespace App\Livewire\Academics\Series;

use App\Domain\Academics\Models\Serie;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $serie = '';

    public string $serie_wording = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Serie::class);
    }

    public function create(): void
    {
        $this->authorize('create', Serie::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $serieId): void
    {
        $serie = Serie::findOrFail($serieId);

        $this->authorize('update', $serie);

        $this->editingId = $serie->id;
        $this->serie = $serie->serie;
        $this->serie_wording = $serie->serie_wording;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'serie' => ['required', 'string', 'max:10', Rule::unique('series', 'serie')->ignore($this->editingId)],
            'serie_wording' => ['required', 'string', 'max:50'],
        ]);

        if ($this->editingId) {
            $serie = Serie::findOrFail($this->editingId);
            $this->authorize('update', $serie);
        } else {
            $this->authorize('create', Serie::class);
            $serie = new Serie;
        }

        $serie->fill($data);
        $serie->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $serieId): void
    {
        $serie = Serie::findOrFail($serieId);

        $this->authorize('delete', $serie);

        $serie->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'serie', 'serie_wording']);
    }

    public function render()
    {
        return view('livewire.academics.series.index', [
            'series' => Serie::orderBy('serie')->get(),
        ])->title(__('Séries'));
    }
}
