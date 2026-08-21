<?php

declare(strict_types=1);

namespace App\Http\Controllers\Academics;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Même principe que les bulletins/reçus : jamais pré-généré ni stocké,
 * rendu à la volée à chaque consultation.
 */
class ClassroomStudentListPdfController extends Controller
{
    public function __invoke(Request $request, Classroom $classroom): Response
    {
        Gate::authorize('view', $classroom);

        $classroom->loadMissing(['level', 'serie', 'schoolYear', 'establishment.inspection.direction']);

        $genderFilter = $request->query('gender');
        $assignedFilter = $request->query('assigned');
        $repeatingFilter = $request->query('repeating');
        $scholarshipFilter = $request->query('scholarship');

        $students = $classroom->enrollments()
            ->where('status', 'active')
            ->when($genderFilter, fn ($query) => $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('gender', $genderFilter)))
            ->when($assignedFilter !== null && $assignedFilter !== '', fn ($query) => $query->where('is_assigned', $assignedFilter === '1'))
            ->when($repeatingFilter !== null && $repeatingFilter !== '', fn ($query) => $query->where('is_repeating', $repeatingFilter === '1'))
            ->when($scholarshipFilter !== null && $scholarshipFilter !== '', fn ($query) => $query->where('is_scholarship', $scholarshipFilter === '1'))
            ->with('student')
            ->get()
            ->pluck('student')
            ->sortBy([['last_name', 'asc'], ['first_name', 'asc']])
            ->values();

        $pdf = Pdf::loadView('pdf.classroom-student-list', [
            'classroom' => $classroom,
            'students' => $students,
            'generalInformation' => GeneralInformation::current(),
        ])->setPaper('a4');

        $filename = Str::slug("liste-eleves-{$classroom->name}").'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
