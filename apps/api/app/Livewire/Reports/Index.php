<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Billing\Models\Payment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?int $school_year_id = null;

    public ?int $classroom_id = null;

    public string $studentSearch = '';

    public ?int $card_student_id = null;

    public ?int $reminderLevelFilter = null;

    public bool $canGenerateReminders = false;

    public string $genderFilter = '';

    public string $assignedFilter = '';

    public string $repeatingFilter = '';

    public string $scholarshipFilter = '';

    public bool $isSecondaire = false;

    /**
     * Bloc « Liste des élèves d'une classe arabe » (école franco-arabe) —
     * voir docs/superpowers/specs/2026-10-20-liste-pdf-classe-arabe-design.md.
     */
    public bool $isArabe = false;

    public ?int $arabic_school_year_id = null;

    public ?int $arabic_classroom_id = null;

    public string $arabicGenderFilter = '';

    public string $arabicRepeatingFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Classroom::class);

        /** @var User $user */
        $user = Auth::user();

        $establishment = Establishment::findOrFail((int) app('currentEstablishmentId'));

        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->canGenerateReminders = $user->can('viewAny', Payment::class);
        $this->isSecondaire = $establishment->type === EstablishmentType::Secondaire;
        $this->isArabe = $establishment->is_arabe;
        $this->arabic_school_year_id = $this->school_year_id;
    }

    public function updatedSchoolYearId(): void
    {
        $this->classroom_id = null;
    }

    public function updatedArabicSchoolYearId(): void
    {
        $this->arabic_classroom_id = null;
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
            'arabicClassrooms' => $this->isArabe && $this->arabic_school_year_id
                ? ArabicClassroom::where('school_year_id', $this->arabic_school_year_id)->orderBy('name')->get()
                : collect(),
        ])->title(__('Listes/Rapports'));
    }
}
