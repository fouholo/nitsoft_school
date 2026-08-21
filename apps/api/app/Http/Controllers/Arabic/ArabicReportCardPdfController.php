<?php

declare(strict_types=1);

namespace App\Http\Controllers\Arabic;

use App\Domain\Arabic\Models\ArabicReportCard;
use App\Domain\Arabic\Services\ArabicReportCardService;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Même principe que le bulletin français : jamais pré-généré ni stocké,
 * rendu à la volée à chaque consultation — voir ReportCardPdfController.
 */
class ArabicReportCardPdfController extends Controller
{
    public function __invoke(Request $request, ArabicReportCard $arabicReportCard, ArabicReportCardService $arabicReportCardService): Response
    {
        Gate::authorize('view', $arabicReportCard);

        $arabicReportCard->loadMissing(['enrollment.student', 'arabicTerm.schoolYear', 'establishment']);

        $pdf = Pdf::loadView('pdf.arabic-report-card', [
            'reportCard' => $arabicReportCard,
            'breakdown' => $arabicReportCardService->subjectBreakdown($arabicReportCard),
            'fontRegularPath' => str_replace('\\', '/', resource_path('fonts/arabic/NotoNaskhArabic-Regular.ttf')),
            'fontBoldPath' => str_replace('\\', '/', resource_path('fonts/arabic/NotoNaskhArabic-Bold.ttf')),
        ])->setPaper('a4');

        $student = $arabicReportCard->enrollment->student;
        $periodSlug = $arabicReportCard->arabicTerm->label;
        $filename = Str::slug("bulletin-arabe-{$student->last_name}-{$student->first_name}-{$periodSlug}").'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
