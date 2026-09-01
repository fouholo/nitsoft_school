<?php

declare(strict_types=1);

namespace App\Livewire\Academics\Timetable;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public Classroom $classroom;

    public bool $canManage = false;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $day_of_week = '';

    public ?int $timetable_slot_id = null;

    public ?int $teacher_assignment_id = null;

    public string $room = '';

    public function mount(Classroom $classroom): void
    {
        $this->authorize('viewAny', TimetableSession::class);

        abort_unless($classroom->level->cycle === Cycle::Secondaire, 404);

        $this->classroom = $classroom;
        $this->canManage = Gate::allows('create', TimetableSession::class);
    }

    public function openCell(string $day, int $slotId): void
    {
        if (! $this->canManage) {
            return;
        }

        $existing = TimetableSession::where('classroom_id', $this->classroom->id)
            ->where('day_of_week', $day)
            ->where('timetable_slot_id', $slotId)
            ->first();

        $this->reset(['editingId', 'teacher_assignment_id', 'room']);
        $this->day_of_week = $day;
        $this->timetable_slot_id = $slotId;

        if ($existing) {
            $this->editingId = $existing->id;
            $this->teacher_assignment_id = TeacherAssignment::where('classroom_id', $this->classroom->id)
                ->where('user_id', $existing->user_id)
                ->where('subject_id', $existing->subject_id)
                ->value('id');
            $this->room = (string) $existing->room;
        }

        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'update' : 'create', $this->editingId ? TimetableSession::findOrFail($this->editingId) : TimetableSession::class);

        $data = $this->validate([
            'teacher_assignment_id' => ['required', 'exists:teacher_classroom_subject,id'],
            'room' => ['nullable', 'string', 'max:255'],
        ]);

        $assignment = TeacherAssignment::where('id', $data['teacher_assignment_id'])
            ->where('classroom_id', $this->classroom->id)
            ->first();

        if (! $assignment) {
            $this->addError('teacher_assignment_id', __("Cet enseignant n'est pas affecté à cette matière pour cette classe."));

            return;
        }

        $conflictQuery = fn () => TimetableSession::where('day_of_week', $this->day_of_week)
            ->where('timetable_slot_id', $this->timetable_slot_id)
            ->when($this->editingId, fn ($query) => $query->where('id', '!=', $this->editingId));

        if ((clone $conflictQuery())->where('classroom_id', $this->classroom->id)->exists()) {
            $this->addError('conflict', __('Cette classe a déjà un cours sur ce créneau.'));

            return;
        }

        if ((clone $conflictQuery())->where('user_id', $assignment->user_id)->exists()) {
            $this->addError('conflict', __('Cet enseignant a déjà un cours sur ce créneau.'));

            return;
        }

        if ($this->room !== '' && (clone $conflictQuery())->where('room', $this->room)->exists()) {
            $this->addError('conflict', __('Cette salle est déjà occupée sur ce créneau.'));

            return;
        }

        $sessionData = [
            'establishment_id' => (int) app('currentEstablishmentId'),
            'school_year_id' => $this->classroom->school_year_id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $assignment->subject_id,
            'user_id' => $assignment->user_id,
            'day_of_week' => $this->day_of_week,
            'timetable_slot_id' => $this->timetable_slot_id,
            'room' => $this->room !== '' ? $this->room : null,
        ];

        if ($this->editingId) {
            TimetableSession::findOrFail($this->editingId)->update($sessionData);
        } else {
            TimetableSession::create($sessionData);
        }

        $this->reset(['editingId', 'teacher_assignment_id', 'room']);
        $this->showForm = false;
    }

    public function delete(): void
    {
        $session = TimetableSession::findOrFail($this->editingId);

        $this->authorize('delete', $session);

        $session->delete();

        $this->reset(['editingId', 'teacher_assignment_id', 'room']);
        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        $sessions = TimetableSession::where('classroom_id', $this->classroom->id)
            ->with(['subject', 'teacher'])
            ->get()
            ->keyBy(fn (TimetableSession $session) => $session->day_of_week->value.'-'.$session->timetable_slot_id);

        return view('livewire.academics.timetable.index', [
            'days' => DayOfWeek::cases(),
            'timetableSlots' => TimetableSlot::orderBy('sequence')->get(),
            'sessions' => $sessions,
            'assignments' => TeacherAssignment::where('classroom_id', $this->classroom->id)
                ->with(['teacher', 'subject'])
                ->get(),
        ])->title(__(':classroom — Emploi du temps', ['classroom' => $this->classroom->name]));
    }
}
