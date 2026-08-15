<?php

declare(strict_types=1);

namespace App\Livewire\Grading\ReportCards\Secondaire;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Term;
use App\Domain\Grading\Models\ReportCard;
use App\Domain\Grading\Services\ReportCardService;
use Illuminate\Support\Collection;
use Livewire\Component;

class Index extends Component
{
    public ?int $classroom_id = null;

    public ?int $term_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ReportCard::class);
    }

    public function updatedClassroomId(): void
    {
        $this->term_id = null;
    }

    public function generate(ReportCardService $service): void
    {
        $this->authorize('create', ReportCard::class);

        $data = $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'term_id' => ['required', 'exists:terms,id'],
        ]);

        $classroom = Classroom::findOrFail($data['classroom_id']);

        $service->generateForClassroomAndTerm($classroom, Term::findOrFail($data['term_id']));
    }

    public function render()
    {
        /** @var Collection<int, ReportCard> $reportCards */
        $reportCards = collect();

        if ($this->classroom_id && $this->term_id) {
            $reportCards = ReportCard::query()
                ->with('student')
                ->where('classroom_id', $this->classroom_id)
                ->where('term_id', $this->term_id)
                ->orderBy('rank')
                ->get();
        }

        return view('livewire.grading.report-cards.secondaire.index', [
            'reportCards' => $reportCards,
            'classrooms' => Classroom::gradable()->whereHas('level', fn ($query) => $query->where('cycle', Cycle::Secondaire))->orderBy('name')->get(),
            'terms' => Term::orderBy('sequence')->get(),
        ]);
    }
}
