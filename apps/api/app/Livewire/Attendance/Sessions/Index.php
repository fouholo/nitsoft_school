<?php

declare(strict_types=1);

namespace App\Livewire\Attendance\Sessions;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Subject;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicSubject;
use App\Domain\Arabic\Models\ArabicTeacherAssignment;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Enums\DayOfWeek;
use App\Domain\Timetable\Models\ArabicTimetableSession;
use App\Domain\Timetable\Models\TimetableSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    /**
     * Filière de la saisie libre (école franco-arabe) : 'francais' ou 'arabe'.
     */
    public string $track = 'francais';

    public ?int $classroom_id = null;

    public ?int $arabic_classroom_id = null;

    public ?int $subject_id = null;

    public ?int $arabic_subject_id = null;

    public string $session_date = '';

    public string $started_at = '';

    public function mount(): void
    {
        $this->authorize('viewAny', AttendanceSession::class);
    }

    public function create(): void
    {
        $this->authorize('create', AttendanceSession::class);

        $this->reset(['track', 'classroom_id', 'arabic_classroom_id', 'subject_id', 'arabic_subject_id']);
        $this->session_date = now()->toDateString();
        $this->started_at = now()->format('H:i');
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('create', AttendanceSession::class);

        if ($this->isArabicEntry()) {
            $this->saveArabic();

            return;
        }

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

    /**
     * Saisie libre d'un appel arabe : même logique que le français, sur la
     * classe et les matières arabes.
     */
    private function saveArabic(): void
    {
        $data = $this->validate([
            'arabic_classroom_id' => ['required', Rule::exists('arabic_classrooms', 'id')->where('establishment_id', (int) app('currentEstablishmentId'))],
            'arabic_subject_id' => ['nullable', 'exists:arabic_subjects,id'],
            'session_date' => ['required', 'date'],
            'started_at' => ['nullable'],
        ], [], [
            'arabic_classroom_id' => __('classe arabe'),
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasAdminRightsOnCurrentEstablishment() && ! $user->isAssignedToArabicClassroom((int) $data['arabic_classroom_id'])) {
            abort(403, __("Vous n'êtes pas affecté à cette classe."));
        }

        $session = AttendanceSession::create([...$data, 'teacher_id' => $user->id]);

        $this->showForm = false;
        $this->redirectRoute('attendance.sessions.mark', $session);
    }

    private function isArabicEntry(): bool
    {
        return $this->track === 'arabe' && $this->currentEstablishmentIsArabe();
    }

    private function currentEstablishmentIsArabe(): bool
    {
        return (bool) Establishment::whereKey((int) app('currentEstablishmentId'))->value('is_arabe');
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    public function startFromTimetable(int $timetableSessionId): void
    {
        $this->authorize('create', AttendanceSession::class);

        $timetableSession = TimetableSession::with('slot')->findOrFail($timetableSessionId);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasAdminRightsOnCurrentEstablishment() && $timetableSession->user_id !== $user->id) {
            abort(403);
        }

        $session = AttendanceSession::where('timetable_session_id', $timetableSession->id)
            ->whereDate('session_date', now()->toDateString())
            ->first();

        if ($session === null) {
            $session = AttendanceSession::create([
                'timetable_session_id' => $timetableSession->id,
                'session_date' => now()->toDateString(),
                'classroom_id' => $timetableSession->classroom_id,
                'subject_id' => $timetableSession->subject_id,
                'teacher_id' => $timetableSession->user_id,
                'started_at' => Carbon::parse($timetableSession->slot->start_time)->format('H:i'),
            ]);
        }

        $this->redirectRoute('attendance.sessions.mark', $session);
    }

    public function startFromArabicTimetable(int $arabicTimetableSessionId): void
    {
        $this->authorize('create', AttendanceSession::class);

        $timetableSession = ArabicTimetableSession::with('slot')->findOrFail($arabicTimetableSessionId);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasAdminRightsOnCurrentEstablishment() && $timetableSession->user_id !== $user->id) {
            abort(403);
        }

        $session = AttendanceSession::where('arabic_timetable_session_id', $timetableSession->id)
            ->whereDate('session_date', now()->toDateString())
            ->first();

        if ($session === null) {
            $session = AttendanceSession::create([
                'arabic_timetable_session_id' => $timetableSession->id,
                'session_date' => now()->toDateString(),
                'arabic_classroom_id' => $timetableSession->arabic_classroom_id,
                'arabic_subject_id' => $timetableSession->arabic_subject_id,
                'teacher_id' => $timetableSession->user_id,
                'started_at' => Carbon::parse($timetableSession->slot?->start_time)->format('H:i'),
            ]);
        }

        $this->redirectRoute('attendance.sessions.mark', $session);
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasAdminRightsOnCurrentEstablishment();

        $sessions = AttendanceSession::query()
            ->with(['classroom', 'subject', 'arabicClassroom', 'arabicSubject'])
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

        $todayDayOfWeek = DayOfWeek::tryFrom(strtolower(now()->format('l')));

        $todaysTimetableSessions = $todayDayOfWeek === null
            ? collect()
            : TimetableSession::where('day_of_week', $todayDayOfWeek)
                ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
                ->with(['classroom', 'subject', 'teacher', 'slot'])
                ->get()
                ->sortBy(fn (TimetableSession $timetableSession) => $timetableSession->slot->sequence);

        $attendedTimetableSessionIds = AttendanceSession::query()
            ->whereDate('session_date', now()->toDateString())
            ->whereNotNull('timetable_session_id')
            ->pluck('id', 'timetable_session_id');

        $isArabe = $this->currentEstablishmentIsArabe();

        $arabicAssignments = $isArabe
            ? ArabicTeacherAssignment::query()
                ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
                ->with(['arabicClassroom', 'arabicSubject'])
                ->get()
            : collect();

        $todaysArabicSessions = $isArabe && $todayDayOfWeek !== null
            ? ArabicTimetableSession::where('day_of_week', $todayDayOfWeek)
                ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
                ->with(['arabicClassroom', 'arabicSubject', 'teacher', 'slot'])
                ->get()
            : collect();

        $attendedArabicSessionIds = $isArabe
            ? AttendanceSession::query()
                ->whereDate('session_date', now()->toDateString())
                ->whereNotNull('arabic_timetable_session_id')
                ->pluck('id', 'arabic_timetable_session_id')
            : collect();

        // Séances du jour des deux filières, triées par heure : les deux
        // grilles de créneaux étant distinctes, seul l'horaire les ordonne.
        $todayEntries = [];

        foreach ($todaysTimetableSessions as $timetableSession) {
            $todayEntries[] = [
                'key' => 'fr-'.$timetableSession->id,
                'id' => $timetableSession->id,
                'arabic' => false,
                'start' => $timetableSession->slot->start_time,
                'slot' => $timetableSession->slot->label,
                'classroom' => $timetableSession->classroom?->name,
                'subject' => $timetableSession->subject?->name,
                'teacher' => $timetableSession->teacher?->name,
                'attendedId' => $attendedTimetableSessionIds->get($timetableSession->id),
            ];
        }

        foreach ($todaysArabicSessions as $arabicSession) {
            if ($arabicSession->slot === null) {
                continue;
            }

            $todayEntries[] = [
                'key' => 'ar-'.$arabicSession->id,
                'id' => $arabicSession->id,
                'arabic' => true,
                'start' => $arabicSession->slot->start_time,
                'slot' => $arabicSession->slot->label,
                'classroom' => $arabicSession->arabicClassroom?->name,
                'subject' => $arabicSession->arabicSubject?->name,
                'teacher' => $arabicSession->teacher?->name,
                'attendedId' => $attendedArabicSessionIds->get($arabicSession->id),
            ];
        }

        usort($todayEntries, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return view('livewire.attendance.sessions.index', [
            'isArabe' => $isArabe,
            'todayEntries' => collect($todayEntries),
            'arabicClassrooms' => ! $isArabe ? collect() : ($isAdmin
                ? ArabicClassroom::whereHas('schoolYear', fn ($query) => $query->where('is_current', true))->orderBy('name')->get()
                : $arabicAssignments->pluck('arabicClassroom')->filter()->unique('id')->sortBy('name')->values()),
            'arabicSubjects' => ! $isArabe ? collect() : ($isAdmin
                ? ArabicSubject::orderBy('name')->get()
                : $arabicAssignments->pluck('arabicSubject')->filter()->unique('id')->sortBy('name')->values()),
            'sessions' => $sessions,
            'pendingToday' => $todaySessions->where('records_count', 0)->count(),
            'todayTotal' => $todaySessions->count(),
            'classrooms' => $isAdmin ? Classroom::orderBy('name')->get() : $assignments->pluck('classroom')->unique('id'),
            'subjects' => $isAdmin ? Subject::orderBy('name')->get() : $assignments->pluck('subject')->unique('id'),
            'isAdmin' => $isAdmin,
            'todaysTimetableSessions' => $todaysTimetableSessions,
            'attendedTimetableSessionIds' => $attendedTimetableSessionIds,
        ])->title(__('Présences'));
    }
}
