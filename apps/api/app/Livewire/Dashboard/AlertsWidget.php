<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Domain\Dashboard\Services\DashboardAlertsService;
use App\Livewire\Dashboard\Concerns\RespondsToEstablishmentFilter;
use Livewire\Component;

class AlertsWidget extends Component
{
    use RespondsToEstablishmentFilter;

    public function render()
    {
        $establishmentIds = $this->authorizedEstablishmentIds();
        $items = [];

        if ($establishmentIds !== []) {
            $service = app(DashboardAlertsService::class);

            $items = [
                ...$service->overdueInvoices($establishmentIds),
                ...$service->classroomsWithoutTeacher($establishmentIds),
                ...$service->classroomsOverCapacity($establishmentIds),
                ...$service->unfinalizedReportCards($establishmentIds),
            ];
        }

        return view('livewire.dashboard.alerts-widget', [
            'items' => $items,
            'showEstablishmentTag' => collect($items)->pluck('establishment_id')->unique()->count() > 1,
        ]);
    }
}
