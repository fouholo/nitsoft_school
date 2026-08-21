<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Services;

use App\Domain\Arabic\Models\ArabicGrade;
use App\Domain\Arabic\Models\ArabicLevel;
use App\Domain\Arabic\Models\ArabicReportCard;
use App\Domain\Arabic\Models\ArabicSerie;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicSubjectCoefficient;
use App\Domain\Arabic\Models\ArabicTerm;
use App\Domain\Arabic\ValueObjects\ArabicSubjectAverage;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Grading\Models\AppreciationScale;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Miroir simplifié de ReportCardService : un seul chemin de calcul (pas de
 * branchement Cycle::Primaire/Secondaire), puisque les coefficients arabes
 * passent toujours par une table de jointure (ArabicSubjectCoefficient), et
 * que le roster est déterminé par (arabic_level_id, arabic_serie_id) plutôt
 * que par une classe française — voir
 * docs/superpowers/specs/2026-08-21-arabe-bulletins-design.md.
 */
class ArabicReportCardService
{
    /**
     * @return Collection<int, ArabicReportCard>
     */
    public function generate(ArabicLevel $level, ?ArabicSerie $serie, ArabicTerm $term): Collection
    {
        $enrollments = $this->enrollmentsFor($level, $serie, $term);

        if ($enrollments->isEmpty()) {
            return collect();
        }

        $grades = ArabicGrade::query()
            ->with('arabicGradeSheet')
            ->whereNotNull('score')
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->whereHas('arabicGradeSheet', fn ($query) => $query->where('arabic_term_id', $term->id))
            ->get();

        $gradesByEnrollment = $grades->groupBy('enrollment_id');

        $coefficients = $this->coefficientsFor($level, $serie);

        $this->assertCoefficientsConfigured($grades, $coefficients);

        $averages = $gradesByEnrollment
            ->map(fn (Collection $grades) => $this->generalAverage($grades, $coefficients))
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

        if (! $enrollment || $enrollment->arabic_level_id === null) {
            return collect();
        }

        $level = ArabicLevel::find($enrollment->arabic_level_id);
        $serie = $enrollment->arabic_serie_id !== null ? ArabicSerie::find($enrollment->arabic_serie_id) : null;

        $grades = ArabicGrade::query()
            ->with('arabicGradeSheet.arabicSubject', 'arabicGradeSheet.teacher')
            ->where('enrollment_id', $arabicReportCard->enrollment_id)
            ->whereNotNull('score')
            ->whereHas('arabicGradeSheet', fn ($query) => $query->where('arabic_term_id', $arabicReportCard->arabic_term_id))
            ->get();

        $coefficients = $level !== null ? $this->coefficientsFor($level, $serie) : collect();

        $rows = [];

        foreach ($grades->groupBy(fn (ArabicGrade $grade) => $grade->arabicGradeSheet->arabic_subject_id) as $subjectId => $subjectGrades) {
            $average = $this->weightedAverage($subjectGrades);

            $rows[] = new ArabicSubjectAverage(
                $subjectGrades->first()->arabicGradeSheet->arabicSubject,
                $average,
                $coefficients->get($subjectId),
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
    private function enrollmentsFor(ArabicLevel $level, ?ArabicSerie $serie, ArabicTerm $term): Collection
    {
        return Enrollment::query()
            ->where('establishment_id', $term->establishment_id)
            ->where('school_year_id', $term->school_year_id)
            ->where('arabic_level_id', $level->id)
            ->where('arabic_serie_id', $serie?->id)
            ->where('status', 'active')
            ->get();
    }

    /**
     * @return Collection<int, float>
     */
    private function coefficientsFor(ArabicLevel $level, ?ArabicSerie $serie): Collection
    {
        $rows = ArabicSubjectCoefficient::query()
            ->where('arabic_level_id', $level->id)
            ->where('arabic_serie_id', $serie?->id)
            ->get();

        $coefficients = [];

        foreach ($rows as $row) {
            $coefficients[(int) $row->arabic_subject_id] = (float) $row->coefficient;
        }

        return collect($coefficients);
    }

    /**
     * @param  Collection<int, ArabicGrade>  $grades
     * @param  Collection<int, float>  $coefficients
     */
    private function assertCoefficientsConfigured(Collection $grades, Collection $coefficients): void
    {
        $subjectIdsGraded = $grades->map(fn (ArabicGrade $grade) => (int) $grade->arabicGradeSheet->arabic_subject_id)->unique();
        $missing = $subjectIdsGraded->diff($coefficients->keys());

        if ($missing->isEmpty()) {
            return;
        }

        $names = ArabicSubject::whereIn('id', $missing)->pluck('name')->implode(', ');

        throw ValidationException::withMessages([
            'arabic_level_id' => "Coefficient manquant pour : {$names}.",
        ]);
    }

    /**
     * @param  Collection<int, ArabicGrade>  $grades
     * @param  Collection<int, float>  $coefficients
     */
    private function generalAverage(Collection $grades, Collection $coefficients): ?float
    {
        $weightedSum = 0.0;
        $totalCoefficient = 0.0;

        foreach ($grades->groupBy(fn (ArabicGrade $grade) => $grade->arabicGradeSheet->arabic_subject_id) as $subjectId => $subjectGrades) {
            $subjectAverage = $this->weightedAverage($subjectGrades);

            if ($subjectAverage === null) {
                continue;
            }

            $coefficient = (float) $coefficients->get($subjectId);
            $weightedSum += $subjectAverage * $coefficient;
            $totalCoefficient += $coefficient;
        }

        if ($totalCoefficient <= 0) {
            return null;
        }

        return round($weightedSum / $totalCoefficient, 2);
    }

    /**
     * @param  Collection<int, ArabicGrade>  $grades
     */
    private function weightedAverage(Collection $grades): ?float
    {
        $totalWeight = $grades->sum(fn (ArabicGrade $grade) => (float) $grade->arabicGradeSheet->weight);

        if ($totalWeight <= 0) {
            return null;
        }

        $weightedSum = $grades->sum(
            fn (ArabicGrade $grade) => ((float) $grade->score / (float) $grade->arabicGradeSheet->max_score) * 20 * (float) $grade->arabicGradeSheet->weight
        );

        return round($weightedSum / $totalWeight, 2);
    }
}
