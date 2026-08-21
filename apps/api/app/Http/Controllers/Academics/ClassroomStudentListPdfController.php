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

        $filterLabels = $this->filterLabels($genderFilter, $assignedFilter, $repeatingFilter, $scholarshipFilter);

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
            'filterLabels' => $filterLabels,
        ])->setPaper('a4');

        $filename = Str::slug("liste-eleves-{$classroom->name}".($filterLabels ? '-'.implode('-', $filterLabels) : '')).'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * @return list<string>
     */
    private function filterLabels(?string $gender, ?string $assigned, ?string $repeating, ?string $scholarship): array
    {
        $labels = [];

        if ($gender === 'm') {
            $labels[] = 'Garçons';
        } elseif ($gender === 'f') {
            $labels[] = 'Filles';
        }

        if ($assigned === '1') {
            $labels[] = 'Affectés';
        } elseif ($assigned === '0') {
            $labels[] = 'Non affectés';
        }

        if ($repeating === '1') {
            $labels[] = 'Redoublants';
        } elseif ($repeating === '0') {
            $labels[] = 'Non redoublants';
        }

        if ($scholarship === '1') {
            $labels[] = 'Boursiers';
        } elseif ($scholarship === '0') {
            $labels[] = 'Non boursiers';
        }

        return $labels;
    }
}
