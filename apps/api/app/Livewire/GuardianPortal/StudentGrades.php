<?php

declare(strict_types=1);

namespace App\Livewire\GuardianPortal;

use App\Domain\Enrollment\Models\Student;
use App\Domain\Grading\Models\Grade;
use App\Livewire\GuardianPortal\Concerns\EnsuresGuardianAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guardian-portal')]
#[Title('Notes')]
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
        return view('livewire.guardian-portal.student-grades', [
            'grades' => Grade::query()
                ->where('student_id', $this->student->id)
                ->whereNotNull('score')
                ->with(['gradeSheet.subject', 'gradeSheet.primarySubject', 'gradeSheet.term'])
                ->get(),
        ]);
    }
}
