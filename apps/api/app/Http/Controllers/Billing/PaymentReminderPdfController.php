<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Services\PaymentTrackingService;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Http\Controllers\Billing\Concerns\BuildsPaymentReminderRows;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lettre de relance pour un seul élève, dans l'un des deux types
 * (paramètre "type") :
 * - "late" (défaut) : solde en retard — jamais générée pour un solde nul
 *   ou négatif.
 * - "upcoming" : rappel pour la prochaine échéance de l'année scolaire non
 *   encore soldée par cet élève — jamais générée si aucune échéance à
 *   venir n'est configurée, ou si l'élève l'a déjà soldée.
 * Dans les deux cas, jamais de lettre "vide", y compris en accès direct
 * par URL.
 */
class PaymentReminderPdfController extends Controller
{
    use BuildsPaymentReminderRows;

    public function __invoke(Request $request, Student $student): Response
    {
        Gate::authorize('view', $student);

        $student->loadMissing('establishment.inspection.direction');

        $schoolYear = SchoolYear::where('is_current', true)->first();

        if ($schoolYear === null) {
            throw new NotFoundHttpException();
        }

        $reminderType = $request->query('type') === 'upcoming' ? 'upcoming' : 'late';
        $trackingService = app(PaymentTrackingService::class);
        $nextInstallment = null;

        if ($reminderType === 'upcoming') {
            $nextInstallment = $trackingService->nextUpcomingInstallment($schoolYear->id);

            if ($nextInstallment === null
                || $trackingService->studentsAwaitingInstallment($nextInstallment, null, $student->id)->isEmpty()) {
                throw new NotFoundHttpException();
            }
        } else {
            $balance = $trackingService->balanceForStudent($student->id, $schoolYear->id);

            if (($balance['balance'] ?? 0) <= 0) {
                throw new NotFoundHttpException();
            }
        }

        $enrollment = $student->enrollments()
            ->where('school_year_id', $schoolYear->id)
            ->where('status', 'active')
            ->with('classroom')
            ->first();

        if ($enrollment === null) {
            throw new NotFoundHttpException();
        }

        $reminder = $this->reminderRowsFor($enrollment);

        $pdf = Pdf::loadView('pdf.payment-reminder', [
            'student' => $student,
            'establishment' => $student->establishment,
            'classroom' => $enrollment->classroom,
            'schoolYear' => $schoolYear,
            'rows' => $reminder['rows'],
            'total' => $reminder['total'],
            'generalInformation' => GeneralInformation::current(),
            'reminderType' => $reminderType,
            'nextInstallment' => $nextInstallment,
        ])->setPaper('a5', 'portrait');

        $filename = Str::slug("relance-{$student->last_name}-{$student->first_name}").'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
