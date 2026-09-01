<?php

declare(strict_types=1);

namespace App\Livewire\Academics\Timetable;

use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\TimetableSession;
use App\Domain\Timetable\Models\TimetableSlot;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MySchedule extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', TimetableSession::class);
    }

    public function render()
    {
        $sessions = TimetableSession::where('user_id', Auth::id())
            ->with(['subject', 'classroom'])
            ->get()
            ->keyBy(fn (TimetableSession $session) => $session->day_of_week->value.'-'.$session->timetable_slot_id);

        return view('livewire.academics.timetable.my-schedule', [
            'days' => DayOfWeek::cases(),
            'timetableSlots' => TimetableSlot::orderBy('sequence')->get(),
            'sessions' => $sessions,
        ])->title(__('Mon emploi du temps'));
    }
}
