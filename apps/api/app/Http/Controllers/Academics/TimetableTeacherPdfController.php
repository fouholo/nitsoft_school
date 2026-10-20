<?php

declare(strict_types=1);

namespace App\Http\Controllers\Academics;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
use App\Domain\Timetable\Services\WeeklyAgenda;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TimetableTeacherPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', TimetableSession::class);

        $user = Auth::user();

        $establishment = Establishment::with(['inspection.direction'])
            ->findOrFail((int) app('currentEstablishmentId'));

        $filename = Str::slug('emploi-du-temps-'.$user->fullName()).'.pdf';

        // École franco-arabe : séances françaises et arabes réunies dans un
        // agenda (les deux grilles de créneaux diffèrent) — voir
        // docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
        if ($establishment->is_arabe) {
            $pdf = Pdf::loadView('pdf.timetable-agenda', [
                'title' => __('Emploi du temps').' — '.$user->fullName(),
                'establishment' => $establishment,
                'generalInformation' => GeneralInformation::current(),
                'agenda' => app(WeeklyAgenda::class)->forTeacher($user->id, $establishment),
                'fontRegularPath' => str_replace('\\', '/', resource_path('fonts/arabic/NotoNaskhArabic-Regular.ttf')),
                'fontBoldPath' => str_replace('\\', '/', resource_path('fonts/arabic/NotoNaskhArabic-Bold.ttf')),
            ])->setPaper('a4', 'landscape');

            return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
        }

        $sessions = TimetableSession::where('user_id', $user->id)
            ->with(['subject', 'classroom'])
            ->get()
            ->keyBy(fn (TimetableSession $session) => $session->day_of_week->value.'-'.$session->timetable_slot_id);

        $pdf = Pdf::loadView('pdf.timetable', [
            'title' => __('Emploi du temps').' — '.$user->fullName(),
            'establishment' => $establishment,
            'generalInformation' => GeneralInformation::current(),
            'days' => DayOfWeek::weekdays(),
            'slots' => TimetableSlot::orderBy('sequence')->get(),
            'sessions' => $sessions,
            'showClassroom' => true,
        ])->setPaper('a4', 'landscape');

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
