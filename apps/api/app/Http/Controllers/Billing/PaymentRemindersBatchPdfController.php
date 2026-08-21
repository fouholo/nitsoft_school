<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\PaymentTrackingService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Http\Controllers\Billing\Concerns\BuildsPaymentReminderRows;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Planche de lettres de relance (une page A5 par élève concerné),
 * filtrable par niveau, dans l'un des deux types (paramètre "type") :
 * - "late" (défaut) : élèves en retard de paiement.
 * - "upcoming" : élèves n'ayant pas encore soldé la prochaine échéance de
 *   l'année scolaire, qu'ils soient par ailleurs en retard ou non.
 * Voir PaymentReminderPdfController pour la génération d'une lettre isolée.
 */
class PaymentRemindersBatchPdfController extends Controller
{
    use BuildsPaymentReminderRows;

    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $schoolYear = SchoolYear::where('is_current', true)->first();
        $levelId = $request->integer('level_id') ?: null;
        $reminderType = $request->query('type') === 'upcoming' ? 'upcoming' : 'late';
        $trackingService = app(PaymentTrackingService::class);
        $nextInstallment = null;

        if ($reminderType === 'upcoming') {
            $nextInstallment = $schoolYear ? $trackingService->nextUpcomingInstallment($schoolYear->id) : null;
            $studentIds = $nextInstallment
                ? $trackingService->studentsAwaitingInstallment($nextInstallment)->pluck('student_id')
                : collect();
        } else {
            $studentIds = $schoolYear
                ? $trackingService->balances($schoolYear->id)
                    ->filter(fn (array $row) => $row['balance'] > 0)
                    ->pluck('student_id')
                : collect();
        }

        $enrollments = Enrollment::whereIn('student_id', $studentIds)
            ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
            ->where('status', 'active')
            ->when($levelId, fn ($query) => $query->whereHas('classroom', fn ($q) => $q->where('level_id', $levelId)))
            ->with(['student', 'classroom', 'establishment.inspection.direction'])
            ->get()
            ->sortBy([['student.last_name', 'asc'], ['student.first_name', 'asc']])
            ->values();

        $letters = $enrollments->map(function (Enrollment $enrollment): array {
            $reminder = $this->reminderRowsFor($enrollment);

            return [
                'student' => $enrollment->student,
                'establishment' => $enrollment->establishment,
                'classroom' => $enrollment->classroom,
                'rows' => $reminder['rows'],
                'total' => $reminder['total'],
            ];
        });

        $pdf = Pdf::loadView('pdf.payment-reminders-batch', [
            'letters' => $letters,
            'schoolYear' => $schoolYear,
            'generalInformation' => GeneralInformation::current(),
            'reminderType' => $reminderType,
            'nextInstallment' => $nextInstallment,
        ])->setPaper('a5', 'portrait');

        $filename = $reminderType === 'upcoming' ? 'rappels-echeance.pdf' : 'lettres-de-relance.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
