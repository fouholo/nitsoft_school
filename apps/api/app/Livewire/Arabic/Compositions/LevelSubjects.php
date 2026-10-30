<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Compositions;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicLevelSubject;
use App\Domain\Arabic\Models\ArabicSubject;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Matières du primaire arabe : l'école choisit, pour chaque niveau, ses
 * matières et leur ordre sur le relevé — voir
 * docs/superpowers/specs/2026-10-30-compositions-primaire-arabe-design.md.
 */
#[Layout('layouts.app')]
class LevelSubjects extends Component
{
    public ?int $arabic_level_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicLevelSubject::class);

        $this->arabic_level_id = ArabicLevel::where('cycle', Cycle::Primaire)->orderBy('wording')->value('id');
    }

    public function add(int $arabicSubjectId): void
    {
        $this->authorize('manage', ArabicLevelSubject::class);

        $levelId = $this->selectedLevelId();
        $subject = ArabicSubject::findOrFail($arabicSubjectId);

        ArabicLevelSubject::firstOrCreate(
            ['establishment_id' => (int) app('currentEstablishmentId'), 'arabic_level_id' => $levelId, 'arabic_subject_id' => $subject->id],
            ['sequence' => 1 + (int) ArabicLevelSubject::where('arabic_level_id', $levelId)->max('sequence')],
        );
    }

    public function remove(int $levelSubjectId): void
    {
        $this->authorize('manage', ArabicLevelSubject::class);

        ArabicLevelSubject::findOrFail($levelSubjectId)->delete();
    }

    public function move(int $levelSubjectId, int $direction): void
    {
        $this->authorize('manage', ArabicLevelSubject::class);

        $ordered = $this->levelSubjects()->values();
        $index = $ordered->search(fn (ArabicLevelSubject $levelSubject) => $levelSubject->id === $levelSubjectId);
        $neighbour = $index === false ? null : $ordered->get($index + ($direction < 0 ? -1 : 1));

        if ($index === false || ! $neighbour instanceof ArabicLevelSubject) {
            return;
        }

        $current = $ordered->get($index);

        // Renumérotation complète : garantit des ordres distincts même après
        // des retraits, avant d'échanger les deux voisins.
        $ordered->each(fn (ArabicLevelSubject $levelSubject, int $position) => $levelSubject->update(['sequence' => $position + 1]));

        $currentSequence = $current->sequence;
        $current->update(['sequence' => $neighbour->sequence]);
        $neighbour->update(['sequence' => $currentSequence]);
    }

    private function selectedLevelId(): int
    {
        $level = ArabicLevel::where('cycle', Cycle::Primaire)->findOrFail($this->arabic_level_id);

        return $level->id;
    }

    /**
     * @return Collection<int, ArabicLevelSubject>
     */
    private function levelSubjects(): Collection
    {
        return ArabicLevelSubject::query()
            ->where('arabic_level_id', $this->arabic_level_id)
            ->with('arabicSubject')
            ->orderBy('sequence')
            ->get()
            ->toBase();
    }

    public function render()
    {
        $levelSubjects = $this->arabic_level_id ? $this->levelSubjects() : collect();

        return view('livewire.arabic.compositions.level-subjects', [
            'arabicLevels' => ArabicLevel::where('cycle', Cycle::Primaire)->orderBy('wording')->get(),
            'levelSubjects' => $levelSubjects,
            'availableSubjects' => ArabicSubject::whereNotIn('id', $levelSubjects->pluck('arabic_subject_id'))->orderBy('name')->get(),
        ])->title(__('Matières du primaire'));
    }
}
