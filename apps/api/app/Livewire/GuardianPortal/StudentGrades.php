<?php

declare(strict_types=1);

namespace App\Livewire\GuardianPortal;

use App\Domain\Enrollment\Models\Student;
use App\Domain\Grading\Models\Grade;
use App\Domain\Grading\Models\PrimaryGrade;
use App\Livewire\GuardianPortal\Concerns\EnsuresGuardianAccess;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guardian-portal')]
class StudentGrades extends Component
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
        $grades = Grade::query()
            ->where('student_id', $this->student->id)
            ->whereNotNull('score')
            ->with(['gradeSheet.subject', 'gradeSheet.term'])
            ->get();

        $primaryGrades = PrimaryGrade::query()
            ->where('student_id', $this->student->id)
            ->whereNotNull('score')
            ->with('gradeSheet.primarySubject')
            ->get();

        return view('livewire.guardian-portal.student-grades', [
            'grades' => $grades->concat($primaryGrades),
        ])->title(__('Notes'));
    }
}
