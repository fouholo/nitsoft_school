<?php

declare(strict_types=1);

namespace App\Livewire\Grading\ReportCards;

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

    public function mount(): void
    {
        $this->authorize('viewAny', ReportCard::class);
    }

    public function generate(ReportCardService $service): void
    {
        $this->authorize('create', ReportCard::class);

        $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'term_id' => ['required', 'exists:terms,id'],
        ]);

        $service->generateForClassroomAndTerm(
            Classroom::findOrFail($this->classroom_id),
            Term::findOrFail($this->term_id),
        );
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

        return view('livewire.grading.report-cards.index', [
            'reportCards' => $reportCards,
            'classrooms' => Classroom::orderBy('name')->get(),
            'terms' => Term::orderBy('sequence')->get(),
        ]);
    }
}
