<?php

declare(strict_types=1);

namespace App\Domain\Timetable\Services;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\ArabicTimetableSession;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;

/**
 * Semaine d'un enseignant d'école franco-arabe, séances françaises et
 * arabes réunies : les deux grilles de créneaux étant distinctes, on
 * présente un agenda (une colonne par jour, séances triées par heure) plutôt
 * qu'une grille par créneau — voir
 * docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
 */
class WeeklyAgenda
{
    /**
     * @return array{days: list<DayOfWeek>, entries: array<string, list<array{start: string, end: string, subject: string, classroom: string, room: string|null, arabic: bool}>>}
     */
    public function forTeacher(int $userId, Establishment $establishment): array
    {
        $entries = [];

        $frenchSessions = TimetableSession::where('user_id', $userId)
            ->with(['slot', 'subject', 'classroom'])
            ->get();

        foreach ($frenchSessions as $session) {
            if ($session->slot === null) {
                continue;
            }

            $entries[$session->day_of_week->value][] = [
                'start' => substr($session->slot->start_time, 0, 5),
                'end' => substr($session->slot->end_time, 0, 5),
                'subject' => (string) $session->subject?->name,
                'classroom' => (string) $session->classroom?->name,
                'room' => $session->room,
                'arabic' => false,
            ];
        }

        $arabicSessions = ArabicTimetableSession::where('user_id', $userId)
            ->with(['slot', 'arabicSubject', 'arabicClassroom'])
            ->get();

        foreach ($arabicSessions as $session) {
            if ($session->slot === null) {
                continue;
            }

            $entries[$session->day_of_week->value][] = [
                'start' => substr($session->slot->start_time, 0, 5),
                'end' => substr($session->slot->end_time, 0, 5),
                'subject' => (string) $session->arabicSubject?->name,
                'classroom' => (string) $session->arabicClassroom?->name,
                'room' => $session->room,
                'arabic' => true,
            ];
        }

        foreach ($entries as $day => $dayEntries) {
            usort($dayEntries, fn (array $a, array $b) => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
            $entries[$day] = $dayEntries;
        }

        $hasFrenchTimetable = $frenchSessions->isNotEmpty() || TimetableSlot::exists();
        $wanted = array_merge(
            $hasFrenchTimetable ? DayOfWeek::weekdays() : [],
            $establishment->arabicTimetableDays(),
            array_map(fn (string $day) => DayOfWeek::from($day), array_keys($entries)),
        );

        return [
            'days' => array_values(array_filter(DayOfWeek::cases(), fn (DayOfWeek $day) => in_array($day, $wanted, true))),
            'entries' => $entries,
        ];
    }
}
