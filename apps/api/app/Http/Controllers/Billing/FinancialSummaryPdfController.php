<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\FinancialSummaryService;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Domain\Establishments\Support\RolePermissions;
use App\Http\Controllers\Controller;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Même résolution de période que Livewire\Billing\FinancialSummary\Index
 * (dupliquée volontairement — voir
 * docs/superpowers/specs/2026-08-21-bilan-financier-par-profil-design.md) :
 * "start_date"/"end_date" pour une plage personnalisée, sinon
 * "school_year_id" (ou l'année scolaire courante par défaut). Même
 * logique de détection "fondateur multi-écoles" que le composant Livewire
 * — voir
 * docs/superpowers/specs/2026-08-21-bilan-financier-fondateur-multi-etablissements-design.md.
 */
class FinancialSummaryPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        /** @var User $user */
        $user = Auth::user();
        $ownerId = RolePermissions::can($user->currentRole(), 'finance.scope_own_only') ? $user->id : null;

        [$start, $end, $periodLabel] = $this->resolvePeriod(
            $request->query('school_year_id') ? (int) $request->query('school_year_id') : null,
            $request->query('start_date'),
            $request->query('end_date'),
        );

        if ($start === null || $end === null) {
            throw new NotFoundHttpException();
        }

        $service = app(FinancialSummaryService::class);
        $groupEstablishments = $user->founderGroupEstablishments();
        $isMultiSchoolFounder = $groupEstablishments->count() >= 2;

        $establishment = Establishment::findOrFail((int) app('currentEstablishmentId'));
        $establishment->loadMissing('inspection.direction');

        $viewData = [
            'establishment' => $establishment,
            'generalInformation' => GeneralInformation::current(),
            'periodLabel' => $periodLabel,
            'isMultiSchoolFounder' => $isMultiSchoolFounder,
        ];

        if ($isMultiSchoolFounder) {
            $requestedIds = array_map('intval', (array) $request->query('establishment_ids', []));
            $allowedIds = $groupEstablishments->pluck('id');
            $establishmentIds = $requestedIds === []
                ? $allowedIds->values()->all()
                : $allowedIds->intersect($requestedIds)->values()->all();

            if ($establishmentIds === []) {
                throw new NotFoundHttpException();
            }

            $establishmentGroups = $service->summaryByEstablishments($start, $end, $establishmentIds, $ownerId);

            $viewData += [
                'establishmentGroups' => $establishmentGroups,
                'grandTotalCollected' => (float) array_sum(array_column($establishmentGroups, 'collected')),
                'grandTotalSpent' => (float) array_sum(array_column($establishmentGroups, 'spent')),
                'grandTotalNet' => (float) array_sum(array_column($establishmentGroups, 'net')),
            ];

            $filename = Str::slug("bilan-financier-groupe-{$periodLabel}").'.pdf';
        } else {
            $summary = $service->summaryByUser($start, $end, (int) app('currentEstablishmentId'), $ownerId);
            $groups = $service->groupByRole($summary);

            $viewData += [
                'groups' => $groups,
                'totalCollected' => (float) array_sum(array_column($groups, 'collected')),
                'totalSpent' => (float) array_sum(array_column($groups, 'spent')),
                'totalNet' => (float) array_sum(array_column($groups, 'net')),
            ];

            $filename = Str::slug("bilan-financier-{$periodLabel}").'.pdf';
        }

        $pdf = Pdf::loadView('pdf.financial-summary', $viewData)->setPaper('a4');

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}|array{0: null, 1: null, 2: null}
     */
    private function resolvePeriod(?int $schoolYearId, ?string $startDate, ?string $endDate): array
    {
        if ($startDate && $endDate) {
            if ($endDate < $startDate) {
                return [null, null, null];
            }

            return [
                CarbonImmutable::parse($startDate)->startOfDay(),
                CarbonImmutable::parse($endDate)->endOfDay(),
                CarbonImmutable::parse($startDate)->format('d-m-Y').'-au-'.CarbonImmutable::parse($endDate)->format('d-m-Y'),
            ];
        }

        $schoolYear = $schoolYearId
            ? SchoolYear::find($schoolYearId)
            : SchoolYear::where('is_current', true)->first();

        if ($schoolYear === null) {
            return [null, null, null];
        }

        return [
            CarbonImmutable::parse($schoolYear->starts_on)->startOfDay(),
            CarbonImmutable::parse($schoolYear->ends_on)->endOfDay(),
            $schoolYear->label,
        ];
    }
}
