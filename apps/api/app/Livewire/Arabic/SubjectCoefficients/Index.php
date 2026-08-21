<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\SubjectCoefficients;

use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicSubjectCoefficient;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Coefficients par matière arabe')]
class Index extends Component
{
    public ?int $arabic_level_id = null;

    public ?int $arabic_serie_id = null;

    /** @var array<int, string> */
    public array $coefficients = [];

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicSubjectCoefficient::class);
    }

    public function updatedArabicLevelId(): void
    {
        $this->arabic_serie_id = null;
        $this->loadCoefficients();
    }

    public function updatedArabicSerieId(): void
    {
        $this->loadCoefficients();
    }

    protected function loadCoefficients(): void
    {
        $this->coefficients = [];

        if (! $this->arabic_level_id) {
            return;
        }

        if ($this->selectedLevelRequiresSeries() && ! $this->arabic_serie_id) {
            return;
        }

        $existing = ArabicSubjectCoefficient::where('arabic_level_id', $this->arabic_level_id)
            ->where('arabic_serie_id', $this->selectedLevelRequiresSeries() ? $this->arabic_serie_id : null)
            ->get()
            ->keyBy('arabic_subject_id');

        foreach ($this->arabicSubjects() as $arabicSubject) {
            $coefficient = $existing->get($arabicSubject->id)?->coefficient;
            $this->coefficients[$arabicSubject->id] = $coefficient !== null ? (string) $coefficient : '';
        }
    }

    /**
     * @return Collection<int, ArabicSubject>
     */
    private function arabicSubjects(): Collection
    {
        return ArabicSubject::orderBy('name')->get();
    }

    public function save(): void
    {
        $this->authorize('create', ArabicSubjectCoefficient::class);

        $rules = [];
        foreach (array_keys($this->coefficients) as $arabicSubjectId) {
            $rules["coefficients.{$arabicSubjectId}"] = ['nullable', 'numeric', 'min:0.5', 'max:20'];
        }

        $this->validate($rules);

        $arabicSerieId = $this->selectedLevelRequiresSeries() ? $this->arabic_serie_id : null;

        foreach ($this->coefficients as $arabicSubjectId => $value) {
            if ($value === '') {
                continue;
            }

            ArabicSubjectCoefficient::updateOrCreate(
                [
                    'establishment_id' => app('currentEstablishmentId'),
                    'arabic_level_id' => $this->arabic_level_id,
                    'arabic_serie_id' => $arabicSerieId,
                    'arabic_subject_id' => $arabicSubjectId,
                ],
                ['coefficient' => $value]
            );
        }
    }

    public function selectedLevelRequiresSeries(): bool
    {
        return $this->arabic_level_id ? (bool) ArabicLevel::whereKey($this->arabic_level_id)->value('requires_series') : false;
    }

    public function render()
    {
        return view('livewire.arabic.subject-coefficients.index', [
            'arabicLevels' => ArabicLevel::orderBy('wording')->get(),
            'arabicSeries' => ArabicSerie::orderBy('serie')->get(),
            'arabicSubjects' => $this->arabicSubjects(),
        ]);
    }
}
