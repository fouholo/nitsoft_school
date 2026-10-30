<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Services;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicGrade;
use App\Domain\Arabic\Models\ArabicReportCard;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Arabic\ValueObjects\ArabicSubjectAverage;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Grading\Models\AppreciationScale;
use Illuminate\Support\Collection;

/**
 * Miroir simplifié de ReportCardService, sans coefficient : la moyenne d'une
 * matière est la moyenne simple de ses évaluations ramenées sur 20, la
 * moyenne générale la moyenne simple des matières. Le roster et le rang sont
 * ceux de la classe arabe, qu'elle soit la classe principale ou secondaire
 * de l'élève — voir docs/superpowers/specs/2026-08-21-arabe-bulletins-design.md
 * et docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */
class ArabicReportCardService
{
    /**
     * @return Collection<int, ArabicReportCard>
     */
    public function generate(ArabicClassroom $arabicClassroom, ArabicTerm $term): Collection
    {
        $enrollments = $this->enrollmentsFor($arabicClassroom, $term);

        if ($enrollments->isEmpty()) {
            return collect();
        }

        $grades = ArabicGrade::query()
            ->with('arabicGradeSheet')
            ->whereNotNull('score')
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->whereHas('arabicGradeSheet', fn ($query) => $query
                ->where('arabic_term_id', $term->id)
                ->where('arabic_classroom_id', $arabicClassroom->id))
            ->get();

        $gradesByEnrollment = $grades->groupBy('enrollment_id');

        $averages = $gradesByEnrollment
            ->map(fn (Collection $grades) => $this->generalAverage($grades))
            ->filter(fn (?float $average) => $average !== null)
            ->sortDesc();

        $reportCards = collect();
        $rank = 0;
        $position = 0;
        $previousAverage = null;

        foreach ($averages as $enrollmentId => $average) {
            $position++;

            if ($average !== $previousAverage) {
                $rank = $position;
            }

            $previousAverage = $average;

            $reportCards->push(ArabicReportCard::updateOrCreate(
                ['enrollment_id' => $enrollmentId, 'arabic_term_id' => $term->id],
                [
                    'establishment_id' => $term->establishment_id,
                    'average' => $average,
                    'rank' => $rank,
                    'appreciation' => AppreciationScale::forAverage($average, 20.0)?->appreciation,
                    'generated_at' => now(),
                ]
            ));
        }

        return $reportCards;
    }

    /**
     * @return Collection<int, ArabicSubjectAverage>
     */
    public function subjectBreakdown(ArabicReportCard $arabicReportCard): Collection
    {
        $enrollment = $arabicReportCard->enrollment;

        $arabicClassroom = $enrollment?->arabicClassroom;

        if ($arabicClassroom === null) {
            return collect();
        }

        $grades = ArabicGrade::query()
            ->with('arabicGradeSheet.arabicSubject', 'arabicGradeSheet.teacher')
            ->where('enrollment_id', $arabicReportCard->enrollment_id)
            ->whereNotNull('score')
            ->whereHas('arabicGradeSheet', fn ($query) => $query->where('arabic_term_id', $arabicReportCard->arabic_term_id))
            ->get();

        $rows = [];

        foreach ($grades->groupBy(fn (ArabicGrade $grade) => $grade->arabicGradeSheet->arabic_subject_id) as $subjectId => $subjectGrades) {
            $average = $this->subjectAverage($subjectGrades);

            $rows[] = new ArabicSubjectAverage(
                $subjectGrades->first()->arabicGradeSheet->arabicSubject,
                $average,
                null,
                $average !== null ? AppreciationScale::forAverage($average, 20.0)?->appreciation : null,
                $subjectGrades->first()->arabicGradeSheet->teacher?->name,
            );
        }

        usort($rows, fn (ArabicSubjectAverage $a, ArabicSubjectAverage $b) => $a->subject->name <=> $b->subject->name);

        return collect($rows);
    }

    /**
     * @return Collection<int, Enrollment>
     */
    private function enrollmentsFor(ArabicClassroom $arabicClassroom, ArabicTerm $term): Collection
    {
        return Enrollment::query()
            ->where('establishment_id', $arabicClassroom->establishment_id)
            ->where('school_year_id', $term->school_year_id)
            ->where('arabic_classroom_id', $arabicClassroom->id)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Moyenne simple des matières de l'élève.
     *
     * @param  Collection<int, ArabicGrade>  $grades
     */
    private function generalAverage(Collection $grades): ?float
    {
        $subjectAverages = $grades
            ->groupBy(fn (ArabicGrade $grade) => $grade->arabicGradeSheet->arabic_subject_id)
            ->map(fn (Collection $subjectGrades) => $this->subjectAverage($subjectGrades))
            ->filter(fn (?float $average) => $average !== null);

        return $subjectAverages->isNotEmpty() ? round($subjectAverages->avg(), 2) : null;
    }

    /**
     * Moyenne simple des évaluations de la matière, ramenées sur 20.
     *
     * @param  Collection<int, ArabicGrade>  $grades
     */
    private function subjectAverage(Collection $grades): ?float
    {
        if ($grades->isEmpty()) {
            return null;
        }

        return round($grades->avg(fn (ArabicGrade $grade) => ((float) $grade->score / (float) $grade->arabicGradeSheet->max_score) * 20), 2);
    }
}
