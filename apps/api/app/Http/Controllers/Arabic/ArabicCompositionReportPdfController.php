<?php

declare(strict_types=1);

namespace App\Http\Controllers\Arabic;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicComposition;
use App\Domain\Arabic\Models\ArabicCompositionResult;
use App\Domain\Arabic\Services\ArabicCompositionCalculator;
use App\Domain\Establishments\Models\Establishment;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Relevé de notes du primaire arabe (A5), un élève ou toute une classe, sur
 * le modèle du livret de composition, rendu à la volée — voir
 * docs/superpowers/specs/2026-10-30-compositions-primaire-arabe-design.md.
 */
class ArabicCompositionReportPdfController extends Controller
{
    public function __construct(private readonly ArabicCompositionCalculator $calculator) {}

    public function result(Request $request, ArabicCompositionResult $arabicCompositionResult): Response
    {
        $arabicClassroom = $arabicCompositionResult->arabicClassroom;
        $arabicComposition = $arabicCompositionResult->arabicComposition;

        abort_unless($arabicClassroom instanceof ArabicClassroom && $arabicComposition instanceof ArabicComposition, 404);

        Gate::authorize('accessClassroom', [ArabicComposition::class, $arabicClassroom]);

        $student = $arabicCompositionResult->enrollment?->student;

        return $this->respond(
            $request,
            $arabicComposition,
            $arabicClassroom,
            [$arabicCompositionResult],
            Str::slug("releve-{$student?->last_name}-{$student?->first_name}-{$arabicComposition->month->frenchLabel()}").'.pdf',
        );
    }

    public function classroom(Request $request, ArabicComposition $arabicComposition, ArabicClassroom $arabicClassroom): Response
    {
        Gate::authorize('accessClassroom', [ArabicComposition::class, $arabicClassroom]);

        $results = ArabicCompositionResult::query()
            ->where('arabic_composition_id', $arabicComposition->id)
            ->where('arabic_classroom_id', $arabicClassroom->id)
            ->whereNotNull('generated_at')
            ->with('enrollment.student')
            ->get()
            ->sortBy(fn (ArabicCompositionResult $result) => [$result->enrollment?->student?->last_name, $result->enrollment?->student?->first_name])
            ->values()
            ->all();

        abort_if($results === [], 404);

        return $this->respond(
            $request,
            $arabicComposition,
            $arabicClassroom,
            $results,
            Str::slug("releves-{$arabicClassroom->name}-{$arabicComposition->month->frenchLabel()}").'.pdf',
        );
    }

    /**
     * @param  list<ArabicCompositionResult>  $results
     */
    private function respond(Request $request, ArabicComposition $arabicComposition, ArabicClassroom $arabicClassroom, array $results, string $filename): Response
    {
        $pdf = Pdf::loadView('pdf.arabic-composition-report', [
            'arabicComposition' => $arabicComposition->loadMissing('schoolYear'),
            'arabicClassroom' => $arabicClassroom,
            'establishment' => Establishment::findOrFail($arabicComposition->establishment_id),
            'headcount' => $this->calculator->headcount($arabicClassroom),
            'reports' => array_map(fn (ArabicCompositionResult $result) => [
                'result' => $result,
                'rows' => $this->calculator->reportRows($result, $arabicClassroom),
            ], $results),
            'fontRegularPath' => str_replace('\\', '/', resource_path('fonts/arabic/NotoNaskhArabic-Regular.ttf')),
            'fontBoldPath' => str_replace('\\', '/', resource_path('fonts/arabic/NotoNaskhArabic-Bold.ttf')),
        ])->setPaper('a5');

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
