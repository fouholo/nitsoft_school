<?php

declare(strict_types=1);

namespace App\Http\Controllers\Academics;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TimetableClassroomPdfController extends Controller
{
    public function __invoke(Request $request, Classroom $classroom): Response
    {
        Gate::authorize('view', $classroom);

        $classroom->loadMissing(['establishment.inspection.direction']);

        $sessions = TimetableSession::where('classroom_id', $classroom->id)
            ->with(['subject', 'teacher'])
            ->get()
            ->keyBy(fn (TimetableSession $session) => $session->day_of_week->value.'-'.$session->timetable_slot_id);

        $pdf = Pdf::loadView('pdf.timetable', [
            'title' => __('Emploi du temps').' — '.$classroom->name,
            'establishment' => $classroom->establishment,
            'generalInformation' => GeneralInformation::current(),
            'days' => DayOfWeek::cases(),
            'slots' => TimetableSlot::orderBy('sequence')->get(),
            'sessions' => $sessions,
            'showClassroom' => false,
        ])->setPaper('a4', 'landscape');

        $filename = Str::slug('emploi-du-temps-'.$classroom->name).'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
