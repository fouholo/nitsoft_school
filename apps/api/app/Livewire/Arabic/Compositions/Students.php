<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Compositions;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicComposition;
use App\Domain\Arabic\Models\ArabicCompositionGrade;
use App\Domain\Enrollment\Models\Enrollment;
use App\Livewire\Arabic\Compositions\Concerns\ListsAccessibleClassrooms;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Élèves d'une classe arabe pour une composition, point d'entrée de la
 * saisie élève par élève.
 */
#[Layout('layouts.app')]
class Students extends Component
{
    use ListsAccessibleClassrooms;

    public ArabicComposition $arabicComposition;

    #[Url(as: 'classe')]
    public ?int $arabic_classroom_id = null;

    public function mount(ArabicComposition $arabicComposition): void
    {
        $this->authorize('viewAny', ArabicComposition::class);

        $this->arabicComposition = $arabicComposition;
    }

    public function render()
    {
        $classrooms = $this->accessibleClassrooms($this->arabicComposition);
        $selected = $classrooms->firstWhere('id', $this->arabic_classroom_id);

        $enrollments = collect();
        $gradeCounts = collect();

        if ($selected instanceof ArabicClassroom) {
            $enrollments = Enrollment::query()
                ->where('arabic_classroom_id', $selected->id)
                ->where('status', 'active')
                ->with('student')
                ->get()
                ->sortBy([['student.last_name', 'asc'], ['student.first_name', 'asc']])
                ->values();

            $gradeCounts = ArabicCompositionGrade::query()
                ->where('arabic_composition_id', $this->arabicComposition->id)
                ->whereIn('enrollment_id', $enrollments->pluck('id'))
                ->where(fn ($query) => $query->whereNotNull('score')->orWhere('is_absent', true))
                ->get()
                ->countBy('enrollment_id');
        }

        return view('livewire.arabic.compositions.students', [
            'classrooms' => $classrooms,
            'selectedClassroom' => $selected,
            'enrollments' => $enrollments,
            'gradeCounts' => $gradeCounts,
        ])->title(__('Composition de :month', ['month' => $this->arabicComposition->month->label()]));
    }
}
