<?php

declare(strict_types=1);

namespace App\Http\Controllers\Arabic;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Domain\Timetable\Models\ArabicTimetableSession;
use App\Domain\Timetable\Models\ArabicTimetableSlot;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Emploi du temps d'une classe arabe, en arabe (RTL) sous l'en-tête officiel
 * français, rendu à la volée — voir
 * docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
 */
class ArabicTimetableClassroomPdfController extends Controller
{
    public function __invoke(Request $request, ArabicClassroom $arabicClassroom): Response
    {
        Gate::authorize('viewAny', ArabicTimetableSession::class);
        Gate::authorize('view', $arabicClassroom);

        $arabicClassroom->loadMissing(['arabicLevel', 'arabicSerie', 'schoolYear']);

        $establishment = Establishment::with('inspection.direction')->findOrFail($arabicClassroom->establishment_id);

        $sessions = ArabicTimetableSession::where('arabic_classroom_id', $arabicClassroom->id)
            ->with(['arabicSubject', 'teacher'])
            ->get()
            ->keyBy(fn (ArabicTimetableSession $session) => $session->day_of_week->value.'-'.$session->arabic_timetable_slot_id);

        $pdf = Pdf::loadView('pdf.arabic-timetable', [
            'arabicClassroom' => $arabicClassroom,
            'establishment' => $establishment,
            'generalInformation' => GeneralInformation::current(),
            'days' => $establishment->arabicTimetableDays(),
            'slots' => ArabicTimetableSlot::orderBy('sequence')->get(),
            'sessions' => $sessions,
            'fontRegularPath' => str_replace('\\', '/', resource_path('fonts/arabic/NotoNaskhArabic-Regular.ttf')),
            'fontBoldPath' => str_replace('\\', '/', resource_path('fonts/arabic/NotoNaskhArabic-Bold.ttf')),
        ])->setPaper('a4', 'landscape');

        $filename = Str::slug("emploi-du-temps-arabe-{$arabicClassroom->name}").'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
