<?php

declare(strict_types=1);

namespace App\Domain\Grading\Services;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Term;
use App\Domain\Grading\Models\Grade;
use App\Domain\Grading\Models\ReportCard;
use Illuminate\Support\Collection;

/**
 * Calcul de la moyenne pondérée (par le coefficient de chaque évaluation,
 * ramené sur 20) et du rang par classe/période. Règle simple assumée pour le
 * MVP — à confirmer avec le client (cf. plan d'architecture, section 8,
 * risque "règles de calcul de bulletin").
 */
class ReportCardService
{
    /**
     * @return Collection<int, ReportCard>
     */
    public function generateForClassroomAndTerm(Classroom $classroom, Term $term): Collection
    {
        $gradesByStudent = Grade::query()
            ->whereNotNull('score')
            ->whereHas('gradeSheet', function ($query) use ($classroom, $term): void {
                $query->where('classroom_id', $classroom->id)->where('term_id', $term->id);
            })
            ->with('gradeSheet')
            ->get()
            ->groupBy('student_id');

        $averages = $gradesByStudent
            ->map(fn (Collection $grades) => $this->weightedAverage($grades))
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
                ['student_id' => $studentId, 'term_id' => $term->id],
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
