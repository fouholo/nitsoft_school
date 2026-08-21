<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Series;

use App\Domain\Arabic\Models\ArabicSerie;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Séries arabes')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $serie = '';

    public string $serie_wording = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicSerie::class);
    }

    public function create(): void
    {
        $this->authorize('create', ArabicSerie::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $arabicSerieId): void
    {
        $arabicSerie = ArabicSerie::findOrFail($arabicSerieId);

        $this->authorize('update', $arabicSerie);

        $this->editingId = $arabicSerie->id;
        $this->serie = $arabicSerie->serie;
        $this->serie_wording = $arabicSerie->serie_wording;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'serie' => ['required', 'string', 'max:20'],
            'serie_wording' => ['required', 'string', 'max:100'],
        ]);

        if ($this->editingId) {
            $arabicSerie = ArabicSerie::findOrFail($this->editingId);
            $this->authorize('update', $arabicSerie);
        } else {
            $this->authorize('create', ArabicSerie::class);
            $arabicSerie = new ArabicSerie;
        }

        $arabicSerie->fill($data);
        $arabicSerie->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $arabicSerieId): void
    {
        $arabicSerie = ArabicSerie::findOrFail($arabicSerieId);

        $this->authorize('delete', $arabicSerie);

        $arabicSerie->delete();
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
        return view('livewire.arabic.series.index', [
            'arabicSeries' => ArabicSerie::orderBy('serie')->get(),
        ]);
    }
}
