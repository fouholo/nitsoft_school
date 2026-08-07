<?php

declare(strict_types=1);

namespace App\Livewire\Students;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Enums\GuardianRelationship;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Fiche élève')]
class Show extends Component
{
    public Student $student;

    public bool $showEnrollmentForm = false;

    public ?int $classroom_id = null;

    public ?int $school_year_id = null;

    public string $enrolled_on = '';

    public bool $showGuardianForm = false;

    public ?int $guardian_id = null;

    public ?string $relationship = null;

    public bool $is_primary_contact = false;

    public string $guardianSearch = '';

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);

        $this->student = $student;
    }

    public function addEnrollment(): void
    {
        $this->authorize('create', Enrollment::class);

        $this->classroom_id = null;
        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->enrolled_on = now()->toDateString();
        $this->showEnrollmentForm = true;
    }

    public function saveEnrollment(): void
    {
        $this->authorize('create', Enrollment::class);

        $data = $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'school_year_id' => ['required', 'exists:school_years,id'],
            'enrolled_on' => ['required', 'date'],
        ]);

        $this->student->enrollments()->create([
            ...$data,
            'status' => 'active',
        ]);

        $this->showEnrollmentForm = false;
    }

    public function cancelEnrollment(): void
    {
        $this->showEnrollmentForm = false;
    }

    public function withdrawEnrollment(int $enrollmentId): void
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);

        $this->authorize('update', $enrollment);

        $enrollment->update(['status' => 'withdrawn']);
    }

    public function addGuardian(): void
    {
        $this->authorize('update', $this->student);

        $this->reset(['guardian_id', 'relationship', 'is_primary_contact', 'guardianSearch']);
        $this->showGuardianForm = true;
    }

    public function saveGuardian(): void
    {
        $this->authorize('update', $this->student);

        $data = $this->validate([
            'guardian_id' => ['required', 'exists:guardians,id'],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'is_primary_contact' => ['boolean'],
        ]);

        DB::transaction(function () use ($data): void {
            if ($data['is_primary_contact']) {
                DB::table('guardian_student')
                    ->where('student_id', $this->student->id)
                    ->update(['is_primary_contact' => false]);
            }

            $this->student->guardians()->syncWithoutDetaching([
                $data['guardian_id'] => [
                    'establishment_id' => $this->student->establishment_id,
                    'status' => GuardianLinkStatus::Approved,
                    'relationship' => $data['relationship'],
                    'is_primary_contact' => $data['is_primary_contact'],
                ],
            ]);
        });

        $this->showGuardianForm = false;
    }

    public function cancelGuardian(): void
    {
        $this->showGuardianForm = false;
    }

    public function removeGuardian(int $guardianId): void
    {
        $this->authorize('update', $this->student);

        $this->student->guardians()->detach($guardianId);
    }

    public function render()
    {
        return view('livewire.students.show', [
            'enrollments' => $this->student->enrollments()->with(['classroom', 'schoolYear'])->latest('enrolled_on')->get(),
            'classrooms' => Classroom::orderBy('name')->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'guardians' => $this->student->guardians()->wherePivot('status', GuardianLinkStatus::Approved)->get(),
            'availableGuardians' => Guardian::query()
                ->when($this->guardianSearch !== '', fn ($query) => $query
                    ->where('last_name', 'like', "%{$this->guardianSearch}%")
                    ->orWhere('first_name', 'like', "%{$this->guardianSearch}%"))
                ->orderBy('last_name')
                ->limit(20)
                ->get(),
        ]);
    }
}
