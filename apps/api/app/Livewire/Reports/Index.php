<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Payment;
use App\Domain\Enrollment\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Listes/Rapports')]
class Index extends Component
{
    public ?int $school_year_id = null;

    public ?int $classroom_id = null;

    public string $studentSearch = '';

    public ?int $card_student_id = null;

    public ?int $reminderLevelFilter = null;

    public bool $canGenerateReminders = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Classroom::class);

        /** @var User $user */
        $user = Auth::user();

        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->canGenerateReminders = $user->can('viewAny', Payment::class);
    }

    public function updatedSchoolYearId(): void
    {
        $this->classroom_id = null;
    }

    public function updatedStudentSearch(): void
    {
        $this->card_student_id = null;
    }

    public function render()
    {
        return view('livewire.reports.index', [
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'classrooms' => $this->school_year_id
                ? Classroom::where('school_year_id', $this->school_year_id)->orderBy('name')->get()
                : collect(),
            'cardStudents' => Student::query()
                ->when($this->studentSearch !== '', fn ($query) => $query
                    ->where('first_name', 'like', "%{$this->studentSearch}%")
                    ->orWhere('last_name', 'like', "%{$this->studentSearch}%")
                    ->orWhere('student_number', 'like', "%{$this->studentSearch}%"))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(50)
                ->get(),
            'levels' => Level::orderBy('level_wording')->get(),
        ]);
    }
}
