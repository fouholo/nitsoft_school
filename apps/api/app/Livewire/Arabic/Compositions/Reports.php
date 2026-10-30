<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Compositions;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicComposition;
use App\Domain\Arabic\Models\ArabicCompositionGrade;
use App\Domain\Arabic\Models\ArabicCompositionResult;
use App\Domain\Arabic\Services\ArabicCompositionCalculator;
use App\Livewire\Arabic\Compositions\Concerns\ListsAccessibleClassrooms;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Génération des relevés d'une classe pour une composition : moyenne, rang,
 * PDF individuels et PDF groupé — voir
 * docs/superpowers/specs/2026-10-30-compositions-primaire-arabe-design.md.
 */
#[Layout('layouts.app')]
class Reports extends Component
{
    use ListsAccessibleClassrooms;

    public ?int $arabic_composition_id = null;

    public ?int $arabic_classroom_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicComposition::class);
    }

    public function updatedArabicCompositionId(): void
    {
        $this->arabic_classroom_id = null;
    }

    public function generate(ArabicCompositionCalculator $calculator): void
    {
        $this->authorize('generate', ArabicComposition::class);

        $arabicComposition = ArabicComposition::find($this->arabic_composition_id);
        $arabicClassroom = $arabicComposition instanceof ArabicComposition
            ? $this->accessibleClassrooms($arabicComposition)->firstWhere('id', $this->arabic_classroom_id)
            : null;

        if (! $arabicClassroom instanceof ArabicClassroom) {
            throw ValidationException::withMessages(['arabic_classroom_id' => __('Choisissez une composition et une classe.')]);
        }

        $calculator->generate($arabicComposition, $arabicClassroom);
    }

    public function render()
    {
        $compositions = ArabicComposition::query()
            ->where('school_year_id', SchoolYear::where('is_current', true)->value('id'))
            ->get()
            ->sortBy(fn (ArabicComposition $composition) => $composition->month->position())
            ->values();

        $selectedComposition = $compositions->firstWhere('id', $this->arabic_composition_id);
        $classrooms = $selectedComposition instanceof ArabicComposition ? $this->accessibleClassrooms($selectedComposition) : collect();
        $selectedClassroom = $classrooms->firstWhere('id', $this->arabic_classroom_id);

        $results = collect();
        $hasStaleResults = false;

        if ($selectedComposition instanceof ArabicComposition && $selectedClassroom instanceof ArabicClassroom) {
            $results = ArabicCompositionResult::query()
                ->where('arabic_composition_id', $selectedComposition->id)
                ->where('arabic_classroom_id', $selectedClassroom->id)
                ->whereNotNull('generated_at')
                ->with('enrollment.student')
                ->get()
                ->sortBy(fn (ArabicCompositionResult $result) => [$result->rank ?? PHP_INT_MAX, $result->enrollment?->student?->last_name])
                ->values();

            // Notes modifiées après la dernière génération : le rang affiché
            // n'est plus à jour.
            $generatedAt = $results->min('generated_at');
            $hasStaleResults = $generatedAt !== null && ArabicCompositionGrade::query()
                ->where('arabic_composition_id', $selectedComposition->id)
                ->whereIn('enrollment_id', $results->pluck('enrollment_id'))
                ->where('updated_at', '>', $generatedAt)
                ->exists();
        }

        return view('livewire.arabic.compositions.reports', [
            'compositions' => $compositions,
            'classrooms' => $classrooms,
            'selectedComposition' => $selectedComposition,
            'selectedClassroom' => $selectedClassroom,
            'results' => $results,
            'hasStaleResults' => $hasStaleResults,
        ])->title(__('Relevés de notes'));
    }
}
