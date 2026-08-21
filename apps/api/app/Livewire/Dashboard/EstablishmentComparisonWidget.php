<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Services\FinancialSummaryService;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Dashboard\Concerns\RespondsToEstablishmentFilter;
use Carbon\CarbonImmutable;
use Livewire\Component;

/**
 * Rendu uniquement pour un fondateur d'un groupe à 2+ établissements (le
 * parent Dashboard.php ne le monte pas sinon). Une carte par établissement
 * sélectionné, avec ses propres chiffres — jamais fusionnés entre écoles.
 */
class EstablishmentComparisonWidget extends Component
{
    use RespondsToEstablishmentFilter;

    public function render()
    {
        $establishmentIds = $this->authorizedEstablishmentIds();

        $establishments = Establishment::whereIn('id', $establishmentIds)->orderBy('name')->get();
        $schoolYear = SchoolYear::where('is_current', true)->first();
        $service = app(FinancialSummaryService::class);

        $cards = $establishments->map(function (Establishment $establishment) use ($schoolYear, $service): array {
            $totals = $schoolYear !== null
                ? $service->totalsForEstablishments(
                    CarbonImmutable::parse($schoolYear->starts_on)->startOfDay(),
                    CarbonImmutable::parse($schoolYear->ends_on)->endOfDay(),
                    [$establishment->id],
                )
                : ['collected' => 0.0, 'spent' => 0.0, 'net' => 0.0];

            return [
                'establishment_id' => $establishment->id,
                'name' => $establishment->name,
                'studentsCount' => Student::withoutTenant()
                    ->where('establishment_id', $establishment->id)
                    ->where('is_active', true)
                    ->count(),
                'collected' => $totals['collected'],
                'spent' => $totals['spent'],
                'net' => $totals['net'],
            ];
        })->all();

        return view('livewire.dashboard.establishment-comparison-widget', [
            'cards' => $cards,
            'noEstablishmentSelected' => $establishmentIds === [],
        ]);
    }
}
