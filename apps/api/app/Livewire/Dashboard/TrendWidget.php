<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Domain\Billing\Services\FinancialSummaryService;
use App\Livewire\Dashboard\Concerns\RespondsToEstablishmentFilter;
use Carbon\CarbonImmutable;
use Livewire\Component;

class TrendWidget extends Component
{
    use RespondsToEstablishmentFilter;

    public function render()
    {
        $establishmentIds = $this->authorizedEstablishmentIds();

        $currentCollected = 0.0;
        $previousCollected = 0.0;

        if ($establishmentIds !== []) {
            $service = app(FinancialSummaryService::class);
            $now = CarbonImmutable::now();
            $previousMonth = $now->subMonthNoOverflow();

            $currentCollected = $service->totalsForEstablishments(
                $now->startOfMonth(),
                $now->endOfMonth(),
                $establishmentIds,
            )['collected'];

            $previousCollected = $service->totalsForEstablishments(
                $previousMonth->startOfMonth(),
                $previousMonth->endOfMonth(),
                $establishmentIds,
            )['collected'];
        }

        $deltaAmount = $currentCollected - $previousCollected;
        $deltaPercent = $previousCollected > 0.0 ? ($deltaAmount / $previousCollected) * 100 : null;

        return view('livewire.dashboard.trend-widget', [
            'currentCollected' => $currentCollected,
            'previousCollected' => $previousCollected,
            'deltaAmount' => $deltaAmount,
            'deltaPercent' => $deltaPercent,
            'noEstablishmentSelected' => $establishmentIds === [],
        ]);
    }
}
