<?php

declare(strict_types=1);

namespace App\Livewire\Academics\SubjectCoefficients;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\Serie;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\SubjectCoefficient;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Coefficients par matière')]
class Index extends Component
{
    public ?int $level_id = null;

    public ?int $serie_id = null;

    /** @var array<int, string> */
    public array $coefficients = [];

    public function mount(): void
    {
        $this->authorize('viewAny', SubjectCoefficient::class);
    }

    public function updatedLevelId(): void
    {
        $this->serie_id = null;
        $this->loadCoefficients();
    }

    public function updatedSerieId(): void
    {
        $this->loadCoefficients();
    }

    protected function loadCoefficients(): void
    {
        $this->coefficients = [];

        if (! $this->level_id) {
            return;
        }

        if ($this->selectedLevelRequiresSeries() && ! $this->serie_id) {
            return;
        }

        $existing = SubjectCoefficient::where('level_id', $this->level_id)
            ->where('serie_id', $this->selectedLevelRequiresSeries() ? $this->serie_id : null)
            ->get()
            ->keyBy('subject_id');

        foreach ($this->subjectsForSelectedLevel() as $subject) {
            $coefficient = $existing->get($subject->id)?->coefficient;
            $this->coefficients[$subject->id] = $coefficient !== null ? (string) $coefficient : '';
        }
    }

    /**
     * @return Collection<int, Subject>
     */
    private function subjectsForSelectedLevel(): Collection
    {
        return Subject::where('is_secondaire', true)->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->authorize('create', SubjectCoefficient::class);

        $rules = [];
        foreach (array_keys($this->coefficients) as $subjectId) {
            $rules["coefficients.{$subjectId}"] = ['nullable', 'numeric', 'min:0.5', 'max:20'];
        }

        $this->validate($rules);

        $serieId = $this->selectedLevelRequiresSeries() ? $this->serie_id : null;

        foreach ($this->coefficients as $subjectId => $value) {
            if ($value === '') {
                continue;
            }

            SubjectCoefficient::updateOrCreate(
                [
                    'establishment_id' => app('currentEstablishmentId'),
                    'level_id' => $this->level_id,
                    'serie_id' => $serieId,
                    'subject_id' => $subjectId,
                ],
                ['coefficient' => $value]
            );
        }
    }

    public function selectedLevelRequiresSeries(): bool
    {
        return $this->level_id ? (bool) Level::whereKey($this->level_id)->value('requires_series') : false;
    }

    public function render()
    {
        return view('livewire.academics.subject-coefficients.index', [
            'levels' => Level::where('cycle', Cycle::Secondaire)->orderBy('level_wording')->get(),
            'series' => Serie::orderBy('serie')->get(),
            'subjects' => $this->subjectsForSelectedLevel(),
        ]);
    }
}
