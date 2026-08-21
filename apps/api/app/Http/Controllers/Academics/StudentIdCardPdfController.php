<?php

declare(strict_types=1);

namespace App\Http\Controllers\Academics;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Student;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Même principe que les autres PDF (bulletins, reçus, listes) : jamais
 * pré-généré ni stocké, rendu à la volée. Page à la taille exacte d'une
 * carte de crédit (ISO/IEC 7810 ID-1) plutôt qu'une feuille A4 — voir
 * pdf/partials/student-id-card-style.blade.php.
 */
class StudentIdCardPdfController extends Controller
{
    public function __invoke(Request $request, Student $student): Response
    {
        Gate::authorize('view', $student);

        $student->loadMissing('establishment');

        $currentSchoolYear = SchoolYear::where('is_current', true)->first();

        $enrollment = $student->enrollments()
            ->where('status', 'active')
            ->when($currentSchoolYear, fn ($query) => $query->where('school_year_id', $currentSchoolYear->id))
            ->with(['classroom', 'schoolYear'])
            ->first();

        $pdf = Pdf::loadView('pdf.student-id-card', [
            'student' => $student,
            'establishment' => $student->establishment,
            'classroom' => $enrollment?->classroom,
            'schoolYear' => $enrollment !== null ? $enrollment->schoolYear : $currentSchoolYear,
        ])->setPaper([0, 0, 242.65, 153.02]);

        $filename = Str::slug("carte-identite-{$student->last_name}-{$student->first_name}").'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
