<?php

declare(strict_types=1);

namespace App\Domain\Grading\Services;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\SubjectCoefficient;
use App\Domain\Academics\Models\Term;
use App\Domain\Grading\Models\Grade;
use App\Domain\Grading\Models\ReportCard;
use App\Domain\Grading\ValueObjects\SubjectAverage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Calcul de la moyenne générale et du rang par classe/période. La moyenne de
 * matière reste un pool pondéré par le poids de chaque évaluation (weightedAverage,
 * inchangé depuis l'origine). La moyenne générale, elle, pondère chaque
 * moyenne de matière par son coefficient (grille SubjectCoefficient par
 * niveau/série — cf. docs/superpowers/specs/2026-08-10-evaluations-primaire-secondaire-design.md).
 */
class ReportCardService
{
    /**
     * @return Collection<int, ReportCard>
     */
    public function generateForClassroomAndTerm(Classroom $classroom, Term $term): Collection
    {
        return $this->generate(
            $classroom,
            fn ($query) => $query->where('term_id', $term->id),
            ['term_id' => $term->id],
        );
    }

    /**
     * @return Collection<int, ReportCard>
     */
    public function generateForClassroomAndComposition(Classroom $classroom, int $compositionNumber): Collection
    {
        return $this->generate(
            $classroom,
            fn ($query) => $query->where('composition_number', $compositionNumber),
            ['school_year_id' => $classroom->school_year_id, 'composition_number' => $compositionNumber],
        );
    }

    /**
     * @param  callable(\Illuminate\Contracts\Database\Query\Builder): \Illuminate\Contracts\Database\Query\Builder  $scopeGradeSheets
     * @param  array<string, int>  $reportCardKey  Clé (hors student_id) passée à updateOrCreate pour identifier le bulletin.
     * @return Collection<int, ReportCard>
     */
    private function generate(Classroom $classroom, callable $scopeGradeSheets, array $reportCardKey): Collection
    {
        if (! $classroom->isGradable()) {
            throw ValidationException::withMessages([
                'classroom_id' => "Ce niveau n'a pas de notation.",
            ]);
        }

        $gradesByStudent = Grade::query()
            ->whereNotNull('score')
            ->whereHas('gradeSheet', function ($query) use ($classroom, $scopeGradeSheets): void {
                $scopeGradeSheets($query->where('classroom_id', $classroom->id));
            })
            ->with('gradeSheet.subject')
            ->get()
            ->groupBy('student_id');

        $coefficients = $this->coefficientsFor($classroom);

        $this->assertCoefficientsConfigured($classroom, $gradesByStudent, $coefficients);

        $averages = $gradesByStudent
            ->map(fn (Collection $grades) => $this->generalAverage($grades, $coefficients))
            ->filter(fn (?float $average) => $average !== null)
            ->sortDesc();

        $reportCards = collect();
        $rank = 0;
        $position = 0;
        $previousAverage = null;

        foreach ($averages as $studentId => $average) {
            $position++;

            if ($average !== $previousAverage) {
                $rank = $position;
            }

            $previousAverage = $average;

            $reportCards->push(ReportCard::updateOrCreate(
                ['student_id' => $studentId, ...$reportCardKey],
                [
                    'establishment_id' => $classroom->establishment_id,
                    'classroom_id' => $classroom->id,
                    'average' => $average,
                    'rank' => $rank,
                    'generated_at' => now(),
                ]
            ));
        }

        return $reportCards;
    }

    /**
     * Détail par matière pour l'affichage du bulletin (PDF) : moyenne
     * pondérée de chaque matière où l'élève a au moins une note sur la
     * période, avec le coefficient de la matière pour ce niveau/série (null
     * si non configuré — pas de blocage ici, uniquement à la génération).
     * N'est pas persisté — calculé à la volée à chaque consultation.
     *
     * @return Collection<int, SubjectAverage>
     */
    public function subjectBreakdown(ReportCard $reportCard): Collection
    {
        $grades = Grade::query()
            ->where('student_id', $reportCard->student_id)
            ->whereNotNull('score')
            ->whereHas('gradeSheet', function ($query) use ($reportCard): void {
                $query->where('classroom_id', $reportCard->classroom_id);

                $reportCard->term_id !== null
                    ? $query->where('term_id', $reportCard->term_id)
                    : $query->where('composition_number', $reportCard->composition_number);
            })
            ->with(['gradeSheet.subject'])
            ->get();

        $coefficients = $reportCard->classroom ? $this->coefficientsFor($reportCard->classroom) : collect();

        $rows = [];

        foreach ($grades->groupBy(fn (Grade $grade) => $grade->gradeSheet->subject_id) as $subjectId => $subjectGrades) {
            $rows[] = new SubjectAverage(
                $this->subjectFor($subjectGrades),
                $this->weightedAverage($subjectGrades),
                $coefficients->get($subjectId)?->coefficient !== null ? (float) $coefficients->get($subjectId)->coefficient : null,
            );
        }

        usort($rows, fn (SubjectAverage $a, SubjectAverage $b) => $a->subject->name <=> $b->subject->name);

        return collect($rows);
    }

    /**
     * @return Collection<int, SubjectCoefficient>
     */
    private function coefficientsFor(Classroom $classroom): Collection
    {
        return SubjectCoefficient::query()
            ->where('level_id', $classroom->level_id)
            ->where('serie_id', $classroom->serie_id)
            ->get()
            ->keyBy('subject_id');
    }

    /**
     * @param  Collection<int, SubjectCoefficient>  $coefficients
     */
    private function assertCoefficientsConfigured(Classroom $classroom, EloquentCollection $gradesByStudent, Collection $coefficients): void
    {
        $subjectIdsGraded = $gradesByStudent->flatten()->pluck('gradeSheet.subject_id')->unique();
        $missing = $subjectIdsGraded->diff($coefficients->keys());

        if ($missing->isEmpty()) {
            return;
        }

        $names = Subject::whereIn('id', $missing)->pluck('name')->implode(', ');
        $serieSuffix = $classroom->serie ? ", série {$classroom->serie->serie_wording}" : '';

        throw ValidationException::withMessages([
            'classroom_id' => "Coefficient manquant pour : {$names} (niveau {$classroom->level->level_wording}{$serieSuffix}).",
        ]);
    }

    /**
     * @param  Collection<int, Grade>  $grades
     * @param  Collection<int, SubjectCoefficient>  $coefficients
     */
    private function generalAverage(Collection $grades, Collection $coefficients): ?float
    {
        $weightedSum = 0.0;
        $totalCoefficient = 0.0;

        foreach ($grades->groupBy(fn (Grade $grade) => $grade->gradeSheet->subject_id) as $subjectId => $subjectGrades) {
            $subjectAverage = $this->weightedAverage($subjectGrades);

            if ($subjectAverage === null) {
                continue;
            }

            $coefficient = (float) $coefficients->get($subjectId)->coefficient;
            $weightedSum += $subjectAverage * $coefficient;
            $totalCoefficient += $coefficient;
        }

        return $totalCoefficient > 0 ? round($weightedSum / $totalCoefficient, 2) : null;
    }

    /**
     * @param  Collection<int, Grade>  $grades
     */
    private function subjectFor(Collection $grades): Subject
    {
        return $grades->first()->gradeSheet->subject;
    }

    /**
     * @param  Collection<int, Grade>  $grades
     */
    private function weightedAverage(Collection $grades): ?float
    {
        $totalWeight = $grades->sum(fn (Grade $grade) => (float) $grade->gradeSheet->weight);

        if ($totalWeight <= 0) {
            return null;
        }

        $weightedSum = $grades->sum(
            fn (Grade $grade) => ((float) $grade->score / (float) $grade->gradeSheet->max_score) * 20 * (float) $grade->gradeSheet->weight
        );

        return round($weightedSum / $totalWeight, 2);
    }
}
