<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Services;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicComposition;
use App\Domain\Arabic\Models\ArabicCompositionGrade;
use App\Domain\Arabic\Models\ArabicCompositionResult;
use App\Domain\Arabic\Models\ArabicLevelSubject;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Grading\Models\AppreciationScale;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Calcul des compositions du primaire arabe : notes sur 10, sans
 * coefficient, moyenne = total ÷ matières notées ; une matière absente ou non
 * saisie ne compte pas — voir
 * docs/superpowers/specs/2026-10-30-compositions-primaire-arabe-design.md.
 */
class ArabicCompositionCalculator
{
    public const MAX_SCORE = 10.0;

    /**
     * Matières du niveau de la classe, dans l'ordre choisi par l'école.
     *
     * @return Collection<int, ArabicLevelSubject>
     */
    public function subjectsFor(ArabicClassroom $arabicClassroom): Collection
    {
        return ArabicLevelSubject::query()
            ->where('establishment_id', $arabicClassroom->establishment_id)
            ->where('arabic_level_id', $arabicClassroom->arabic_level_id)
            ->with('arabicSubject')
            ->orderBy('sequence')
            ->get()
            ->toBase();
    }

    /**
     * @param  iterable<array{score: mixed, is_absent: bool}>  $grades
     * @return array{total: float, count: int, average: ?float, mention: ?string}
     */
    public function summarize(iterable $grades): array
    {
        $total = 0.0;
        $count = 0;

        foreach ($grades as $grade) {
            if ($grade['is_absent'] || $grade['score'] === null || $grade['score'] === '' || ! is_numeric($grade['score'])) {
                continue;
            }

            $total += (float) $grade['score'];
            $count++;
        }

        $average = $count > 0 ? round($total / $count, 2) : null;

        return [
            'total' => round($total, 2),
            'count' => $count,
            'average' => $average,
            'mention' => $average !== null ? AppreciationScale::forAverage($average, self::MAX_SCORE)?->appreciation : null,
        ];
    }

    /**
     * Fige total, moyenne, mention et rang des élèves actifs de la classe.
     * L'appréciation déjà saisie est conservée ; le résultat d'un élève qui
     * a quitté la classe est retiré, pour ne pas fausser le classement.
     *
     * @return Collection<int, ArabicCompositionResult>
     */
    public function generate(ArabicComposition $arabicComposition, ArabicClassroom $arabicClassroom): Collection
    {
        $subjectIds = $this->subjectsFor($arabicClassroom)->pluck('arabic_subject_id');

        if ($subjectIds->isEmpty()) {
            throw ValidationException::withMessages([
                'arabic_classroom_id' => __('Aucune matière n’est choisie pour le niveau de cette classe.'),
            ]);
        }

        $enrollmentIds = $this->activeEnrollmentIds($arabicClassroom);

        ArabicCompositionResult::query()
            ->where('arabic_composition_id', $arabicComposition->id)
            ->where('arabic_classroom_id', $arabicClassroom->id)
            ->whereNotIn('enrollment_id', $enrollmentIds)
            ->delete();

        $gradesByEnrollment = ArabicCompositionGrade::query()
            ->where('arabic_composition_id', $arabicComposition->id)
            ->whereIn('enrollment_id', $enrollmentIds)
            ->whereIn('arabic_subject_id', $subjectIds)
            ->get()
            ->groupBy('enrollment_id');

        $summaries = $enrollmentIds->mapWithKeys(fn (int $enrollmentId) => [
            $enrollmentId => $this->summarize(
                $gradesByEnrollment->get($enrollmentId, collect())
                    ->map(fn (ArabicCompositionGrade $grade) => ['score' => $grade->score, 'is_absent' => $grade->is_absent])
            ),
        ]);

        $ranks = $this->ranks($summaries->map(fn (array $summary) => $summary['average'])->all());

        return $summaries
            ->map(fn (array $summary, int $enrollmentId) => ArabicCompositionResult::updateOrCreate(
                ['arabic_composition_id' => $arabicComposition->id, 'enrollment_id' => $enrollmentId],
                [
                    'establishment_id' => $arabicComposition->establishment_id,
                    'arabic_classroom_id' => $arabicClassroom->id,
                    'total' => $summary['average'] !== null ? $summary['total'] : null,
                    'average' => $summary['average'],
                    'mention' => $summary['mention'],
                    'rank' => $ranks[$enrollmentId] ?? null,
                    'generated_at' => now(),
                ],
            ))
            ->values();
    }

    /**
     * Nombre d'élèves actifs de la classe.
     */
    public function headcount(ArabicClassroom $arabicClassroom): int
    {
        return $this->activeEnrollmentIds($arabicClassroom)->count();
    }

    /**
     * Lignes du relevé : matières du niveau, dans l'ordre, avec la note.
     *
     * @return list<array{arabicName: string, frenchName: ?string, score: ?string, isAbsent: bool}>
     */
    public function reportRows(ArabicCompositionResult $result, ArabicClassroom $arabicClassroom): array
    {
        $grades = ArabicCompositionGrade::query()
            ->where('arabic_composition_id', $result->arabic_composition_id)
            ->where('enrollment_id', $result->enrollment_id)
            ->get()
            ->keyBy('arabic_subject_id');

        return array_values($this->subjectsFor($arabicClassroom)->map(function (ArabicLevelSubject $levelSubject) use ($grades): array {
            $grade = $grades->get($levelSubject->arabic_subject_id);

            return [
                'arabicName' => (string) $levelSubject->arabicSubject?->name,
                'frenchName' => $levelSubject->arabicSubject?->name_fr,
                'score' => $grade instanceof ArabicCompositionGrade ? $grade->score : null,
                'isAbsent' => $grade instanceof ArabicCompositionGrade && $grade->is_absent,
            ];
        })->all());
    }

    /**
     * @return Collection<int, int>
     */
    private function activeEnrollmentIds(ArabicClassroom $arabicClassroom): Collection
    {
        return Enrollment::query()
            ->where('establishment_id', $arabicClassroom->establishment_id)
            ->where('arabic_classroom_id', $arabicClassroom->id)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn (mixed $id) => (int) $id)
            ->toBase();
    }

    /**
     * Rang par moyenne décroissante, ex æquo au même rang ; les élèves sans
     * moyenne ne sont pas classés.
     *
     * @param  array<int, ?float>  $averages
     * @return array<int, int>
     */
    private function ranks(array $averages): array
    {
        $ranks = [];
        $position = 0;
        $rank = 0;
        $previous = null;

        $ranked = array_filter($averages, fn (?float $average) => $average !== null);
        arsort($ranked);

        foreach ($ranked as $enrollmentId => $average) {
            $position++;

            if ($average !== $previous) {
                $rank = $position;
            }

            $previous = $average;
            $ranks[$enrollmentId] = $rank;
        }

        return $ranks;
    }
}
