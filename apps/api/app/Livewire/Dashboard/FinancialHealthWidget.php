<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Services\FinancialSummaryService;
use App\Livewire\Dashboard\Concerns\RespondsToEstablishmentFilter;
use Carbon\CarbonImmutable;
use Livewire\Component;

class FinancialHealthWidget extends Component
{
    use RespondsToEstablishmentFilter;

    public function render()
    {
        $establishmentIds = $this->authorizedEstablishmentIds();
        $schoolYear = SchoolYear::where('is_current', true)->first();

        $totals = ($establishmentIds !== [] && $schoolYear !== null)
            ? app(FinancialSummaryService::class)->totalsForEstablishments(
                CarbonImmutable::parse($schoolYear->starts_on)->startOfDay(),
                CarbonImmutable::parse($schoolYear->ends_on)->endOfDay(),
                $establishmentIds,
            )
            : ['collected' => 0.0, 'spent' => 0.0, 'net' => 0.0];

        return view('livewire.dashboard.financial-health-widget', [
            'totals' => $totals,
            'schoolYear' => $schoolYear,
            'noEstablishmentSelected' => $establishmentIds === [],
        ]);
    }
}
