<?php

declare(strict_types=1);

namespace App\Domain\Arabic\Services;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use Illuminate\Support\Collection;

/**
 * Élèves d'une classe arabe (inscriptions actives, qu'elle soit leur classe
 * principale ou secondaire), triés par nom puis prénom, avec les filtres de
 * la liste PDF — voir
 * docs/superpowers/specs/2026-10-20-liste-pdf-classe-arabe-design.md.
 */
class ArabicClassroomRoster
{
    /**
     * @param  string|null  $gender  'm', 'f' ou vide (tous)
     * @param  string|null  $repeating  '1', '0' ou vide (tous), sur le redoublement arabe
     * @return Collection<int, Student>
     */
    public function students(ArabicClassroom $arabicClassroom, ?string $gender = null, ?string $repeating = null): Collection
    {
        return Enrollment::query()
            ->where('establishment_id', $arabicClassroom->establishment_id)
            ->where('arabic_classroom_id', $arabicClassroom->id)
            ->where('status', 'active')
            ->when(in_array($gender, ['m', 'f'], true), fn ($query) => $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('gender', $gender)))
            ->when(in_array($repeating, ['0', '1'], true), fn ($query) => $query->where('is_arabic_repeating', $repeating === '1'))
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->sortBy([['last_name', 'asc'], ['first_name', 'asc']])
            ->values();
    }

    /**
     * Libellés arabes des filtres actifs, pour le sous-titre du PDF.
     *
     * @return list<string>
     */
    public function filterLabels(?string $gender, ?string $repeating): array
    {
        $labels = [];

        if ($gender === 'm') {
            $labels[] = 'ذكور';
        } elseif ($gender === 'f') {
            $labels[] = 'إناث';
        }

        if ($repeating === '1') {
            $labels[] = 'معيدون';
        } elseif ($repeating === '0') {
            $labels[] = 'غير معيدين';
        }

        return $labels;
    }
}
