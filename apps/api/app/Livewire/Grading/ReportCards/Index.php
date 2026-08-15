<?php

declare(strict_types=1);

namespace App\Livewire\Grading\ReportCards;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Term;
use App\Domain\Grading\Models\ReportCard;
use App\Domain\Grading\Services\ReportCardService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Bulletins')]
class Index extends Component
{
    public ?int $classroom_id = null;

    public ?int $term_id = null;

    public ?int $composition_number = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ReportCard::class);
    }

    public function updatedClassroomId(): void
    {
        $this->term_id = null;
        $this->composition_number = null;
    }

    public function generate(ReportCardService $service): void
    {
        $this->authorize('create', ReportCard::class);

        $isPrimaire = $this->isPrimaire();

        $data = $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'term_id' => $isPrimaire ? ['prohibited'] : ['required', 'exists:terms,id'],
            'composition_number' => $isPrimaire ? ['required', 'integer', 'min:1', 'max:10'] : ['prohibited'],
        ]);

        $classroom = Classroom::findOrFail($data['classroom_id']);

        if ($isPrimaire) {
            $service->generateForClassroomAndComposition($classroom, $data['composition_number']);
        } else {
            $service->generateForClassroomAndTerm($classroom, Term::findOrFail($data['term_id']));
        }
    }

    public function selectedClassroomCycle(): ?Cycle
    {
        return $this->classroom_id ? Classroom::find($this->classroom_id)?->level?->cycle : null;
    }

    private function isPrimaire(): bool
    {
        return $this->selectedClassroomCycle() === Cycle::Primaire;
    }

    public function render()
    {
        /** @var Collection<int, ReportCard> $reportCards */
        $reportCards = collect();

        if ($this->classroom_id && $this->isPrimaire() && $this->composition_number) {
            $classroom = Classroom::find($this->classroom_id);

            $reportCards = ReportCard::query()
                ->with('student')
                ->where('classroom_id', $this->classroom_id)
                ->where('school_year_id', $classroom?->school_year_id)
                ->where('composition_number', $this->composition_number)
                ->orderBy('rank')
                ->get();
        } elseif ($this->classroom_id && ! $this->isPrimaire() && $this->term_id) {
            $reportCards = ReportCard::query()
                ->with('student')
                ->where('classroom_id', $this->classroom_id)
                ->where('term_id', $this->term_id)
                ->orderBy('rank')
                ->get();
        }

        return view('livewire.grading.report-cards.index', [
            'reportCards' => $reportCards,
            'classrooms' => Classroom::gradable()->orderBy('name')->get(),
            'terms' => Term::orderBy('sequence')->get(),
        ]);
    }
}
