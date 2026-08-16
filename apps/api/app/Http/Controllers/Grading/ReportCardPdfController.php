<?php

declare(strict_types=1);

namespace App\Http\Controllers\Grading;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Domain\Grading\Models\ReportCard;
use App\Domain\Grading\Services\ReportCardService;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Le bulletin PDF n'est jamais pré-généré ni stocké : il est rendu à la
 * volée à chaque consultation (affichage inline par défaut, téléchargement
 * sur demande via ?download=1) — choix explicite du client, pas d'export
 * automatique.
 */
class ReportCardPdfController extends Controller
{
    public function __invoke(Request $request, ReportCard $reportCard, ReportCardService $reportCardService): Response
    {
        Gate::authorize('view', $reportCard);

        $reportCard->loadMissing(['student', 'classroom.level', 'term.schoolYear', 'schoolYear', 'establishment.inspection.direction']);

        $isPrimaireStyle = $reportCard->classroom->level->cycle !== Cycle::Secondaire;

        $pdf = $isPrimaireStyle
            ? Pdf::loadView('pdf.report-card-primaire', [
                'reportCard' => $reportCard,
                'rows' => $reportCardService->primaryGradeRows($reportCard),
                'generalInformation' => GeneralInformation::current(),
            ])->setPaper('a5')
            : Pdf::loadView('pdf.report-card', [
                'reportCard' => $reportCard,
                'breakdown' => $reportCardService->subjectBreakdown($reportCard),
            ])->setPaper('a4');

        $periodSlug = $reportCard->term_id !== null ? $reportCard->term->label : "Composition {$reportCard->composition_number}";
        $filename = Str::slug("bulletin-{$reportCard->student->last_name}-{$reportCard->student->first_name}-{$periodSlug}").'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
