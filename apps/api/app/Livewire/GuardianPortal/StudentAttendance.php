<?php

declare(strict_types=1);

namespace App\Livewire\GuardianPortal;

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Enrollment\Models\Student;
use App\Livewire\GuardianPortal\Concerns\EnsuresGuardianAccess;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guardian-portal')]
class StudentAttendance extends Component
{
    use EnsuresGuardianAccess;

    public Student $student;

    public function mount(Student $student): void
    {
        $this->authorizeGuardianAccess($student);

        $this->student = $student;
    }

    public function render()
    {
        return view('livewire.guardian-portal.student-attendance', [
            'records' => AttendanceRecord::query()
                ->where('student_id', $this->student->id)
                ->with('session')
                ->get()
                ->sortByDesc(fn (AttendanceRecord $record) => $record->session?->session_date),
        ])->title(__('Présences'));
    }
}
