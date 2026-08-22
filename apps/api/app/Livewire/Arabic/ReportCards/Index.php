<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\ReportCards;

use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicReportCard;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Arabic\Services\ArabicReportCardService;
use App\Domain\Enrollment\Models\Enrollment;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?int $arabic_level_id = null;

    public ?int $arabic_serie_id = null;

    public ?int $arabic_term_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ArabicReportCard::class);
    }

    public function updatedArabicLevelId(): void
    {
        $this->arabic_serie_id = null;
    }

    public function selectedLevelRequiresSeries(): bool
    {
        return $this->arabic_level_id ? (bool) ArabicLevel::whereKey($this->arabic_level_id)->value('requires_series') : false;
    }

    public function generate(ArabicReportCardService $service): void
    {
        $this->authorize('create', ArabicReportCard::class);

        $data = $this->validate([
            'arabic_level_id' => ['required', 'exists:arabic_levels,id'],
            'arabic_serie_id' => $this->selectedLevelRequiresSeries() ? ['required', 'exists:arabic_series,id'] : ['nullable'],
            'arabic_term_id' => ['required', 'exists:arabic_terms,id'],
        ], [], [
            'arabic_level_id' => __('niveau arabe'),
            'arabic_serie_id' => __('série arabe'),
            'arabic_term_id' => __('période'),
        ]);

        $level = ArabicLevel::findOrFail($data['arabic_level_id']);
        $serie = $this->selectedLevelRequiresSeries() ? ArabicSerie::findOrFail($data['arabic_serie_id']) : null;
        $term = ArabicTerm::findOrFail($data['arabic_term_id']);

        $service->generate($level, $serie, $term);
    }

    public function render()
    {
        /** @var Collection<int, ArabicReportCard> $reportCards */
        $reportCards = collect();

        if ($this->arabic_level_id && $this->arabic_term_id) {
            $reportCards = ArabicReportCard::query()
                ->with('enrollment.student')
                ->where('arabic_term_id', $this->arabic_term_id)
                ->whereHas('enrollment', function ($query): void {
                    $query->where('arabic_level_id', $this->arabic_level_id)
                        ->where('arabic_serie_id', $this->selectedLevelRequiresSeries() ? $this->arabic_serie_id : null);
                })
                ->orderBy('rank')
                ->get();
        }

        $totalStudents = $this->arabic_level_id
            ? Enrollment::query()
                ->where('arabic_level_id', $this->arabic_level_id)
                ->where('arabic_serie_id', $this->selectedLevelRequiresSeries() ? $this->arabic_serie_id : null)
                ->where('status', 'active')
                ->count()
            : null;

        return view('livewire.arabic.report-cards.index', [
            'reportCards' => $reportCards,
            'arabicLevels' => ArabicLevel::orderBy('wording')->get(),
            'arabicSeries' => ArabicSerie::orderBy('serie')->get(),
            'arabicTerms' => ArabicTerm::orderBy('sequence')->get(),
            'totalStudents' => $totalStudents,
            'generatedAt' => $reportCards->max('generated_at'),
        ])->title(__('Bulletins arabes'));
    }
}
