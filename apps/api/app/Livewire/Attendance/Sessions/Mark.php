<?php

declare(strict_types=1);

namespace App\Livewire\Attendance\Sessions;

use App\Domain\Attendance\Events\StudentMarkedAbsent;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Enrollment\Models\Student;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title("Faire l'appel")]
class Mark extends Component
{
    public AttendanceSession $session;

    /** @var array<int, string> */
    public array $statuses = [];

    /** @var array<int, string> */
    public array $notes = [];

    public bool $justSaved = false;

    public function mount(AttendanceSession $session): void
    {
        $this->authorize('update', $session);

        $this->session = $session;

        $existingRecords = $session->records()->get()->keyBy('student_id');

        foreach ($this->students() as $student) {
            $record = $existingRecords->get($student->id);
            $status = $record instanceof AttendanceRecord ? $record->status : 'present';
            $note = $record instanceof AttendanceRecord ? $record->note : '';

            $this->statuses[$student->id] = $status;
            $this->notes[$student->id] = $note ?? '';
        }
    }

    public function save(): void
    {
        $this->authorize('update', $this->session);

        $rules = [];
        foreach (array_keys($this->statuses) as $studentId) {
            $rules["statuses.{$studentId}"] = ['required', 'in:present,absent,late,excused'];
        }

        $this->validate($rules);

        $previousStatuses = $this->session->records()->pluck('status', 'student_id');

        foreach ($this->statuses as $studentId => $status) {
            $record = AttendanceRecord::updateOrCreate(
                ['attendance_session_id' => $this->session->id, 'student_id' => $studentId],
                [
                    'status' => $status,
                    'note' => $this->notes[$studentId] !== '' ? $this->notes[$studentId] : null,
                ]
            );

            if ($status === 'absent' && $previousStatuses->get($studentId) !== 'absent') {
                StudentMarkedAbsent::dispatch($record);
            }
        }

        $this->justSaved = true;
    }

    /**
     * @return Collection<int, Student>
     */
    protected function students()
    {
        return Student::query()
            ->whereHas('enrollments', function ($query): void {
                $query->where('classroom_id', $this->session->classroom_id)
                    ->where('status', 'active');
            })
            ->orderBy('last_name')
            ->get();
    }

    public function render()
    {
        return view('livewire.attendance.sessions.mark', [
            'students' => $this->students(),
        ]);
    }
}
