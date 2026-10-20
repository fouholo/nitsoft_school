<?php

declare(strict_types=1);

namespace App\Livewire\Academics\Timetable;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
use App\Domain\Timetable\Services\WeeklyAgenda;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TeacherSchedule extends Component
{
    public User $teacher;

    public function mount(User $user): void
    {
        $this->authorize('browseStaff', TimetableSession::class);

        $isActiveTeacherHere = $user->establishments()
            ->wherePivot('role', 'enseignant')
            ->wherePivot('is_active', true)
            ->where('establishments.id', (int) app('currentEstablishmentId'))
            ->exists();

        abort_unless($isActiveTeacherHere, 404);

        $this->teacher = $user;
    }

    public function render()
    {
        $establishment = Establishment::findOrFail((int) app('currentEstablishmentId'));

        $sessions = TimetableSession::where('user_id', $this->teacher->id)
            ->with(['subject', 'classroom'])
            ->get()
            ->keyBy(fn (TimetableSession $session) => $session->day_of_week->value.'-'.$session->timetable_slot_id);

        return view('livewire.academics.timetable.teacher-schedule', [
            'days' => DayOfWeek::weekdays(),
            'timetableSlots' => TimetableSlot::orderBy('sequence')->get(),
            'sessions' => $sessions,
            // École franco-arabe : séances françaises et arabes réunies.
            'agenda' => $establishment->is_arabe ? app(WeeklyAgenda::class)->forTeacher($this->teacher->id, $establishment) : null,
        ])->title(__(':teacher — Emploi du temps', ['teacher' => $this->teacher->name]));
    }
}
