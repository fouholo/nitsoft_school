<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Timetable;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\ArabicTimetableSession;
use App\Domain\Timetable\Models\ArabicTimetableSlot;
use App\Domain\Timetable\Services\CrossTrackConflicts;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Grille hebdomadaire d'une classe arabe, miroir de
 * Academics\Timetable\Index, avec les jours et créneaux arabes de l'école et
 * les conflits avec l'emploi du temps français — voir
 * docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public ArabicClassroom $arabicClassroom;

    public bool $canManage = false;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $day_of_week = '';

    public ?int $arabic_timetable_slot_id = null;

    public ?int $teacher_assignment_id = null;

    public string $room = '';

    public function mount(ArabicClassroom $arabicClassroom): void
    {
        $this->authorize('viewAny', ArabicTimetableSession::class);

        $this->arabicClassroom = $arabicClassroom;
        $this->canManage = Gate::allows('create', ArabicTimetableSession::class);
    }

    public function openCell(string $day, int $slotId): void
    {
        if (! $this->canManage) {
            return;
        }

        $existing = ArabicTimetableSession::where('arabic_classroom_id', $this->arabicClassroom->id)
            ->where('day_of_week', $day)
            ->where('arabic_timetable_slot_id', $slotId)
            ->first();

        $this->resetErrorBag();
        $this->reset(['editingId', 'teacher_assignment_id', 'room']);
        $this->day_of_week = $day;
        $this->arabic_timetable_slot_id = $slotId;

        if ($existing) {
            $this->editingId = $existing->id;
            $this->teacher_assignment_id = ArabicTeacherAssignment::where('arabic_classroom_id', $this->arabicClassroom->id)
                ->where('user_id', $existing->user_id)
                ->where('arabic_subject_id', $existing->arabic_subject_id)
                ->value('id');
            $this->room = (string) $existing->room;
        }

        $this->showForm = true;
    }

    public function save(CrossTrackConflicts $crossTrackConflicts): void
    {
        $existing = $this->editingId ? ArabicTimetableSession::findOrFail($this->editingId) : null;

        $this->authorize($existing ? 'update' : 'create', $existing ?? ArabicTimetableSession::class);

        $this->validate([
            'teacher_assignment_id' => ['required', 'integer'],
            'room' => ['nullable', 'string', 'max:255'],
        ], [], [
            'teacher_assignment_id' => __('enseignant / matière'),
            'room' => __('salle'),
        ]);

        $day = DayOfWeek::tryFrom($this->day_of_week);
        $slot = ArabicTimetableSlot::find($this->arabic_timetable_slot_id);

        if ($day === null || $slot === null || $slot->is_break || ! in_array($day, $this->establishment()->arabicTimetableDays(), true)) {
            $this->addError('conflict', __('Ce créneau n’est pas un créneau de cours arabe.'));

            return;
        }

        $assignment = ArabicTeacherAssignment::where('id', $this->teacher_assignment_id)
            ->where('arabic_classroom_id', $this->arabicClassroom->id)
            ->first();

        if (! $assignment) {
            $this->addError('teacher_assignment_id', __("Cet enseignant n'est pas affecté à cette matière pour cette classe."));

            return;
        }

        $room = trim($this->room) !== '' ? trim($this->room) : null;

        $sameSlot = fn () => ArabicTimetableSession::where('school_year_id', $this->arabicClassroom->school_year_id)
            ->where('day_of_week', $day)
            ->where('arabic_timetable_slot_id', $slot->id)
            ->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId));

        $conflict = match (true) {
            $sameSlot()->where('arabic_classroom_id', $this->arabicClassroom->id)->exists() => __('Cette classe a déjà un cours sur ce créneau.'),
            $sameSlot()->where('user_id', $assignment->user_id)->exists() => __('Cet enseignant a déjà un cours sur ce créneau.'),
            $room !== null && $sameSlot()->where('room', $room)->exists() => __('Cette salle est déjà occupée sur ce créneau.'),
            default => $crossTrackConflicts->forArabicSession(
                $this->arabicClassroom->school_year_id,
                $this->arabicClassroom->id,
                $assignment->user_id,
                $room,
                $day,
                $slot->start_time,
                $slot->end_time,
            ),
        };

        if ($conflict !== null) {
            $this->addError('conflict', $conflict);

            return;
        }

        $sessionData = [
            'establishment_id' => (int) app('currentEstablishmentId'),
            'school_year_id' => $this->arabicClassroom->school_year_id,
            'arabic_classroom_id' => $this->arabicClassroom->id,
            'arabic_subject_id' => $assignment->arabic_subject_id,
            'user_id' => $assignment->user_id,
            'day_of_week' => $day,
            'arabic_timetable_slot_id' => $slot->id,
            'room' => $room,
        ];

        if ($existing) {
            $existing->update($sessionData);
        } else {
            ArabicTimetableSession::create($sessionData);
        }

        $this->reset(['editingId', 'teacher_assignment_id', 'room']);
        $this->showForm = false;
    }

    public function delete(): void
    {
        $session = ArabicTimetableSession::findOrFail($this->editingId);

        $this->authorize('delete', $session);

        $session->delete();

        $this->reset(['editingId', 'teacher_assignment_id', 'room']);
        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->resetErrorBag();
        $this->showForm = false;
    }

    private function establishment(): Establishment
    {
        return Establishment::findOrFail((int) app('currentEstablishmentId'));
    }

    public function render()
    {
        $sessions = ArabicTimetableSession::where('arabic_classroom_id', $this->arabicClassroom->id)
            ->with(['arabicSubject', 'teacher'])
            ->get()
            ->keyBy(fn (ArabicTimetableSession $session) => $session->day_of_week->value.'-'.$session->arabic_timetable_slot_id);

        return view('livewire.arabic.timetable.index', [
            'days' => $this->establishment()->arabicTimetableDays(),
            'timetableSlots' => ArabicTimetableSlot::orderBy('sequence')->get(),
            'sessions' => $sessions,
            'assignments' => ArabicTeacherAssignment::where('arabic_classroom_id', $this->arabicClassroom->id)
                ->with(['teacher', 'arabicSubject'])
                ->get(),
        ])->title(__(':classroom — Emploi du temps', ['classroom' => $this->arabicClassroom->name]));
    }
}
