<?php

declare(strict_types=1);

namespace App\Http\Controllers\Arabic;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Services\ArabicClassroomRoster;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Liste des élèves d'une classe arabe, en arabe (RTL) sous l'en-tête
 * officiel français — même principe que ClassroomStudentListPdfController :
 * rendue à la volée, jamais stockée. Voir
 * docs/superpowers/specs/2026-10-20-liste-pdf-classe-arabe-design.md.
 */
class ArabicClassroomStudentListPdfController extends Controller
{
    public function __invoke(Request $request, ArabicClassroom $arabicClassroom, ArabicClassroomRoster $roster): Response
    {
        Gate::authorize('view', $arabicClassroom);

        $arabicClassroom->loadMissing(['arabicLevel', 'arabicSerie', 'schoolYear', 'establishment.inspection.direction']);

        $gender = $request->query('gender');
        $repeating = $request->query('repeating');
        $gender = is_string($gender) ? $gender : null;
        $repeating = is_string($repeating) ? $repeating : null;

        $pdf = Pdf::loadView('pdf.arabic-classroom-student-list', [
            'arabicClassroom' => $arabicClassroom,
            'students' => $roster->students($arabicClassroom, $gender, $repeating),
            'filterLabels' => $roster->filterLabels($gender, $repeating),
            'generalInformation' => GeneralInformation::current(),
            'fontRegularPath' => str_replace('\\', '/', resource_path('fonts/arabic/NotoNaskhArabic-Regular.ttf')),
            'fontBoldPath' => str_replace('\\', '/', resource_path('fonts/arabic/NotoNaskhArabic-Bold.ttf')),
        ])->setPaper('a4');

        $filename = Str::slug("liste-eleves-arabe-{$arabicClassroom->name}").'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
