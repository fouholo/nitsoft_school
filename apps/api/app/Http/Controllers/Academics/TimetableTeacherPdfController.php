<?php

declare(strict_types=1);

namespace App\Http\Controllers\Academics;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
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

        $sessions = TimetableSession::where('user_id', $user->id)
            ->with(['subject', 'classroom'])
            ->get()
            ->keyBy(fn (TimetableSession $session) => $session->day_of_week->value.'-'.$session->timetable_slot_id);

        $pdf = Pdf::loadView('pdf.timetable', [
            'title' => __('Emploi du temps').' — '.$user->name,
            'establishment' => $establishment,
            'generalInformation' => GeneralInformation::current(),
            'days' => DayOfWeek::cases(),
            'slots' => TimetableSlot::orderBy('sequence')->get(),
            'sessions' => $sessions,
            'showClassroom' => true,
        ])->setPaper('a4', 'landscape');

        $filename = Str::slug('emploi-du-temps-'.$user->name).'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
