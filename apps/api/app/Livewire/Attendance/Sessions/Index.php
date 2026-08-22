<?php

declare(strict_types=1);

namespace App\Livewire\Attendance\Sessions;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $classroom_id = null;

    public ?int $subject_id = null;

    public string $session_date = '';

    public string $started_at = '';

    public function mount(): void
    {
        $this->authorize('viewAny', AttendanceSession::class);
    }

    public function create(): void
    {
        $this->authorize('create', AttendanceSession::class);

        $this->reset(['classroom_id', 'subject_id']);
        $this->session_date = now()->toDateString();
        $this->started_at = now()->format('H:i');
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('create', AttendanceSession::class);

        $data = $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'session_date' => ['required', 'date'],
            'started_at' => ['nullable'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasAdminRightsOnCurrentEstablishment() && ! $user->isAssignedToClassroom((int) $data['classroom_id'])) {
            abort(403, __("Vous n'êtes pas affecté à cette classe."));
        }

        $session = AttendanceSession::create([...$data, 'teacher_id' => $user->id]);

        $this->showForm = false;

        $this->redirectRoute('attendance.sessions.mark', $session);
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasAdminRightsOnCurrentEstablishment();

        $sessions = AttendanceSession::query()
            ->with(['classroom', 'subject'])
            ->withCount([
                'records',
                'records as absences_count' => fn ($query) => $query->whereIn('status', ['absent', 'late', 'excused']),
            ])
            ->when(! $isAdmin, fn ($query) => $query->where('teacher_id', $user->id))
            ->orderByDesc('session_date')
            ->get();

        $todaySessions = $sessions->filter(fn ($session) => $session->session_date->isToday());

        $assignments = TeacherAssignment::query()
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
            ->with(['classroom', 'subject'])
            ->get();

        return view('livewire.attendance.sessions.index', [
            'sessions' => $sessions,
            'pendingToday' => $todaySessions->where('records_count', 0)->count(),
            'todayTotal' => $todaySessions->count(),
            'classrooms' => $isAdmin ? Classroom::orderBy('name')->get() : $assignments->pluck('classroom')->unique('id'),
            'subjects' => $isAdmin ? Subject::orderBy('name')->get() : $assignments->pluck('subject')->unique('id'),
        ])->title(__('Présences'));
    }
}
