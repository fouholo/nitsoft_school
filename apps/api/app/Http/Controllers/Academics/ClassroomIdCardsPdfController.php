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
 * Planche A4 de cartes (une par élève actif de la classe, taille carte de
 * crédit chacune) prête à découper — voir StudentIdCardPdfController pour
 * la génération d'une carte isolée à la taille exacte de la carte.
 */
class ClassroomIdCardsPdfController extends Controller
{
    public function __invoke(Request $request, Classroom $classroom): Response
    {
        Gate::authorize('view', $classroom);

        $classroom->loadMissing(['establishment', 'schoolYear']);

        $students = $classroom->enrollments()
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->pluck('student')
            ->sortBy([['last_name', 'asc'], ['first_name', 'asc']])
            ->values();

        $pdf = Pdf::loadView('pdf.classroom-id-cards', [
            'classroom' => $classroom,
            'students' => $students,
            'schoolYear' => $classroom->schoolYear,
            'cardBackgroundPath' => GeneralInformation::current()->card_background_path,
        ])->setPaper('a4');

        $filename = Str::slug("cartes-identite-{$classroom->name}").'.pdf';

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
