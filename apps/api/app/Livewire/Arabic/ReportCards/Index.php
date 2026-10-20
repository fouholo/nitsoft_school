<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\ReportCards;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicReportCard;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Arabic\Services\ArabicReportCardService;
use App\Domain\Enrollment\Models\Enrollment;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?int $arabic_classroom_id = null;

    public ?int $arabic_term_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicReportCard::class);
    }

    public function generate(ArabicReportCardService $service): void
    {
        $this->authorize('create', ArabicReportCard::class);

        $data = $this->validate([
            'arabic_classroom_id' => ['required', Rule::exists('arabic_classrooms', 'id')->where('establishment_id', (int) app('currentEstablishmentId'))],
            'arabic_term_id' => ['required', 'exists:arabic_terms,id'],
        ], [], [
            'arabic_classroom_id' => __('classe arabe'),
            'arabic_term_id' => __('période'),
        ]);

        $service->generate(
            ArabicClassroom::findOrFail($data['arabic_classroom_id']),
            ArabicTerm::findOrFail($data['arabic_term_id']),
        );
    }

    public function render()
    {
        /** @var Collection<int, ArabicReportCard> $reportCards */
        $reportCards = collect();

        if ($this->arabic_classroom_id && $this->arabic_term_id) {
            $reportCards = ArabicReportCard::query()
                ->with('enrollment.student')
                ->where('arabic_term_id', $this->arabic_term_id)
                ->whereHas('enrollment', fn ($query) => $query->where('arabic_classroom_id', $this->arabic_classroom_id))
                ->orderBy('rank')
                ->get();
        }

        $totalStudents = $this->arabic_classroom_id
            ? Enrollment::query()
                ->where('arabic_classroom_id', $this->arabic_classroom_id)
                ->where('status', 'active')
                ->count()
            : null;

        return view('livewire.arabic.report-cards.index', [
            'reportCards' => $reportCards,
            'arabicClassrooms' => ArabicClassroom::query()
                ->whereHas('schoolYear', fn ($query) => $query->where('is_current', true))
                ->orderBy('name')
                ->get(),
            'arabicTerms' => ArabicTerm::orderBy('sequence')->get(),
            'totalStudents' => $totalStudents,
            'generatedAt' => $reportCards->max('generated_at'),
        ])->title(__('Bulletins arabes'));
    }
}
