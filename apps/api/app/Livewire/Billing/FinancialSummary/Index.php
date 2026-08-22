<?php

declare(strict_types=1);

namespace App\Livewire\Billing\FinancialSummary;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\FinancialSummaryService;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?int $school_year_id = null;

    public bool $useCustomRange = false;

    public ?string $start_date = null;

    public ?string $end_date = null;

    /**
     * @var list<int>
     */
    public array $establishmentFilter = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Payment::class);

        /** @var User $user */
        $user = Auth::user();

        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');

        $groupEstablishments = $user->founderGroupEstablishments();

        if ($groupEstablishments->count() >= 2) {
            $this->establishmentFilter = $groupEstablishments->pluck('id')->all();
        }
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|array{0: null, 1: null}
     */
    private function resolvePeriod(): array
    {
        if ($this->useCustomRange) {
            if (! $this->start_date || ! $this->end_date || $this->end_date < $this->start_date) {
                return [null, null];
            }

            return [
                CarbonImmutable::parse($this->start_date)->startOfDay(),
                CarbonImmutable::parse($this->end_date)->endOfDay(),
            ];
        }

        $schoolYear = $this->school_year_id ? SchoolYear::find($this->school_year_id) : null;

        if ($schoolYear === null) {
            return [null, null];
        }

        return [
            CarbonImmutable::parse($schoolYear->starts_on)->startOfDay(),
            CarbonImmutable::parse($schoolYear->ends_on)->endOfDay(),
        ];
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        $ownerId = RolePermissions::can($user->currentRole(), 'finance.scope_own_only') ? $user->id : null;

        [$start, $end] = $this->resolvePeriod();

        $invalidRange = $this->useCustomRange && $this->start_date && $this->end_date && $this->end_date < $this->start_date;

        $groupEstablishments = $user->founderGroupEstablishments();
        $isMultiSchoolFounder = $groupEstablishments->count() >= 2;

        $groups = [];
        $establishmentGroups = [];
        $selectedEstablishmentIds = [];
        $noEstablishmentSelected = false;

        if ($start !== null && $end !== null) {
            $service = app(FinancialSummaryService::class);

            if ($isMultiSchoolFounder) {
                // Jamais confiance dans establishmentFilter tel quel : on ne
                // requête que les établissements réellement autorisés pour
                // cet utilisateur, recalculés ci-dessus côté serveur.
                $selectedEstablishmentIds = $groupEstablishments->pluck('id')
                    ->intersect($this->establishmentFilter)
                    ->values()
                    ->all();

                $noEstablishmentSelected = $selectedEstablishmentIds === [];

                if (! $noEstablishmentSelected) {
                    $establishmentGroups = $service->summaryByEstablishments($start, $end, $selectedEstablishmentIds, $ownerId);
                }
            } else {
                $summary = $service->summaryByUser($start, $end, (int) app('currentEstablishmentId'), $ownerId);
                $groups = $service->groupByRole($summary);
            }
        }

        return view('livewire.billing.financial-summary.index', [
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'groups' => $groups,
            'totalCollected' => (float) array_sum(array_column($groups, 'collected')),
            'totalSpent' => (float) array_sum(array_column($groups, 'spent')),
            'totalNet' => (float) array_sum(array_column($groups, 'net')),
            'invalidRange' => $invalidRange,
            'scopedToOwn' => $ownerId !== null,
            'isMultiSchoolFounder' => $isMultiSchoolFounder,
            'groupEstablishments' => $groupEstablishments,
            'establishmentGroups' => $establishmentGroups,
            'noEstablishmentSelected' => $noEstablishmentSelected,
            'grandTotalCollected' => (float) array_sum(array_column($establishmentGroups, 'collected')),
            'grandTotalSpent' => (float) array_sum(array_column($establishmentGroups, 'spent')),
            'grandTotalNet' => (float) array_sum(array_column($establishmentGroups, 'net')),
        ])->title(__('Bilan financier'));
    }
}
