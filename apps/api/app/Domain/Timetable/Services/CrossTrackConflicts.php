<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Services;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\ArabicTimetableSession;
use App\Domain\Timetable\Models\TimetableSession;
use Illuminate\Support\Collection;

/**
 * Conflits entre l'emploi du temps français et l'emploi du temps arabe d'une
 * école franco-arabe : même enseignant, même salle, ou élèves communs (classe
 * française et classe arabe partageant des inscriptions actives) sur des
 * plages qui se chevauchent le même jour. Les deux grilles de créneaux étant
 * distinctes, la comparaison porte sur les horaires réels — voir
 * docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
 */
class CrossTrackConflicts
{
    /**
     * Deux plages qui se touchent (10:00–11:00 et 11:00–12:00) ne se
     * chevauchent pas.
     */
    public static function overlaps(string $startA, string $endA, string $startB, string $endB): bool
    {
        return self::time($startA) < self::time($endB) && self::time($startB) < self::time($endA);
    }

    /**
     * Séance arabe à placer, confrontée aux séances françaises.
     */
    public function forArabicSession(int $schoolYearId, int $arabicClassroomId, int $userId, ?string $room, DayOfWeek $day, string $start, string $end): ?string
    {
        $frenchSessions = TimetableSession::query()
            ->with(['slot', 'classroom'])
            ->where('school_year_id', $schoolYearId)
            ->where('day_of_week', $day)
            ->get()
            ->filter(fn (TimetableSession $session) => $session->slot !== null
                && self::overlaps($start, $end, $session->slot->start_time, $session->slot->end_time));

        if ($frenchSessions->isEmpty()) {
            return null;
        }

        $teacherConflict = $frenchSessions->first(fn (TimetableSession $session) => $session->user_id === $userId);

        if ($teacherConflict !== null) {
            return __('Cet enseignant a déjà un cours en français sur cette plage (:classroom).', ['classroom' => $teacherConflict->classroom?->name]);
        }

        if ($room !== null && $room !== '' && $frenchSessions->contains(fn (TimetableSession $session) => $session->room === $room)) {
            return __('Cette salle est déjà occupée par un cours en français sur cette plage.');
        }

        $sharedStudents = $this->sharedStudentCounts('arabic_classroom_id', $arabicClassroomId, 'classroom_id');

        foreach ($frenchSessions as $session) {
            $count = $sharedStudents->get($session->classroom_id);

            if ($count !== null) {
                return trans_choice(
                    '{1} 1 élève de cette classe a cours en français sur cette plage (:classroom).|[2,*] :count élèves de cette classe ont cours en français sur cette plage (:classroom).',
                    $count,
                    ['count' => $count, 'classroom' => $session->classroom?->name],
                );
            }
        }

        return null;
    }

    /**
     * Séance française à placer, confrontée aux séances arabes.
     */
    public function forFrenchSession(int $schoolYearId, int $classroomId, int $userId, ?string $room, DayOfWeek $day, string $start, string $end): ?string
    {
        $arabicSessions = ArabicTimetableSession::query()
            ->with(['slot', 'arabicClassroom'])
            ->where('school_year_id', $schoolYearId)
            ->where('day_of_week', $day)
            ->get()
            ->filter(fn (ArabicTimetableSession $session) => $session->slot !== null
                && self::overlaps($start, $end, $session->slot->start_time, $session->slot->end_time));

        if ($arabicSessions->isEmpty()) {
            return null;
        }

        $teacherConflict = $arabicSessions->first(fn (ArabicTimetableSession $session) => $session->user_id === $userId);

        if ($teacherConflict !== null) {
            return __('Cet enseignant a déjà un cours en arabe sur cette plage (:classroom).', ['classroom' => $teacherConflict->arabicClassroom?->name]);
        }

        if ($room !== null && $room !== '' && $arabicSessions->contains(fn (ArabicTimetableSession $session) => $session->room === $room)) {
            return __('Cette salle est déjà occupée par un cours en arabe sur cette plage.');
        }

        $sharedStudents = $this->sharedStudentCounts('classroom_id', $classroomId, 'arabic_classroom_id');

        foreach ($arabicSessions as $session) {
            $count = $sharedStudents->get($session->arabic_classroom_id);

            if ($count !== null) {
                return trans_choice(
                    '{1} 1 élève de cette classe a cours en arabe sur cette plage (:classroom).|[2,*] :count élèves de cette classe ont cours en arabe sur cette plage (:classroom).',
                    $count,
                    ['count' => $count, 'classroom' => $session->arabicClassroom?->name],
                );
            }
        }

        return null;
    }

    /**
     * Nombre d'inscriptions actives que la classe donnée partage avec chaque
     * classe de l'autre filière, indexé par l'identifiant de cette classe.
     *
     * @return Collection<int, int>
     */
    private function sharedStudentCounts(string $column, int $classroomId, string $otherColumn): Collection
    {
        return Enrollment::query()
            ->toBase()
            ->where($column, $classroomId)
            ->where('status', 'active')
            ->whereNotNull($otherColumn)
            ->whereNull('deleted_at')
            ->selectRaw("{$otherColumn} AS other_id, COUNT(*) AS shared")
            ->groupBy($otherColumn)
            ->pluck('shared', 'other_id')
            ->mapWithKeys(fn (mixed $shared, mixed $otherId) => [(int) $otherId => (int) $shared]);
    }

    private static function time(string $value): string
    {
        return substr($value, 0, 5);
    }
}
