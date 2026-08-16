<?php

declare(strict_types=1);

namespace App\Livewire\Grading\ReportCards\Primaire;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Grading\Models\ReportCard;
use App\Domain\Grading\Services\ReportCardService;
use Illuminate\Support\Collection;
use Livewire\Component;

class Index extends Component
{
    public ?int $classroom_id = null;

    public ?int $composition_number = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ReportCard::class);
    }

    public function updatedClassroomId(): void
    {
        $this->composition_number = null;
    }

    public function generate(ReportCardService $service): void
    {
        $this->authorize('create', ReportCard::class);

        $data = $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'composition_number' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $classroom = Classroom::findOrFail($data['classroom_id']);

        $service->generateForClassroomAndComposition($classroom, $data['composition_number']);
    }

    public function render()
    {
        /** @var Collection<int, ReportCard> $reportCards */
        $reportCards = collect();

        if ($this->classroom_id && $this->composition_number) {
            $classroom = Classroom::find($this->classroom_id);

            $reportCards = ReportCard::query()
                ->with('student')
                ->where('classroom_id', $this->classroom_id)
                ->where('school_year_id', $classroom?->school_year_id)
                ->where('composition_number', $this->composition_number)
                ->whereNotNull('average')
                ->orderBy('rank')
                ->get();
        }

        return view('livewire.grading.report-cards.primaire.index', [
            'reportCards' => $reportCards,
            'classrooms' => Classroom::gradable()->whereHas('level', fn ($query) => $query->where('cycle', Cycle::Primaire))->orderBy('name')->get(),
        ]);
    }
}
