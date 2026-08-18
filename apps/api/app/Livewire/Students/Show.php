<?php

declare(strict_types=1);

namespace App\Livewire\Students;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Services\PaymentTrackingService;
use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Enums\GuardianRelationship;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

    public bool $is_repeating = false;

    public bool $is_scholarship = false;

    public bool $is_boarding = false;

    public bool $is_assigned = false;

    public bool $showGuardianForm = false;

    public ?int $guardian_id = null;

    public ?string $relationship = null;

    public bool $is_primary_contact = false;

    public string $guardianSearch = '';

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);

        $this->student = $student->loadMissing('nationalite');
    }

    public function addEnrollment(): void
    {
        $this->authorize('create', Enrollment::class);

        $this->classroom_id = null;
        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->enrolled_on = now()->toDateString();
        $this->is_repeating = false;
        $this->is_scholarship = false;
        $this->is_boarding = false;
        $this->is_assigned = false;
        $this->showEnrollmentForm = true;
    }

    public function saveEnrollment(): void
    {
        $this->authorize('create', Enrollment::class);

        $data = $this->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'school_year_id' => ['required', 'exists:school_years,id'],
            'enrolled_on' => ['required', 'date'],
            'is_repeating' => ['boolean'],
            'is_scholarship' => ['boolean'],
            'is_boarding' => ['boolean'],
            'is_assigned' => ['boolean'],
        ]);

        // Défense en profondeur : ces statuts ne sont proposés que pour le
        // secondaire côté vue, mais on ignore toute valeur transmise pour
        // une classe d'un autre cycle plutôt que de faire confiance au client.
        if (Classroom::find($data['classroom_id'])?->level?->cycle !== Cycle::Secondaire) {
            $data['is_repeating'] = false;
            $data['is_scholarship'] = false;
            $data['is_boarding'] = false;
            $data['is_assigned'] = false;
        }

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
        /** @var User $user */
        $user = Auth::user();

        $ownerId = RolePermissions::can($user->currentRole(), 'finance.scope_own_only') ? $user->id : null;

        $schoolYearId = $this->student->enrollments()->where('status', 'active')->latest('enrolled_on')->value('school_year_id')
            ?? SchoolYear::where('is_current', true)->value('id');

        $financialSummary = $schoolYearId
            ? app(PaymentTrackingService::class)->balanceForStudent($this->student->id, $schoolYearId, $ownerId)
            : null;

        $isSecondaireClassroom = $this->classroom_id
            ? Classroom::find($this->classroom_id)?->level?->cycle === Cycle::Secondaire
            : false;

        return view('livewire.students.show', [
            'enrollments' => $this->student->enrollments()->with(['classroom', 'schoolYear'])->latest('enrolled_on')->get(),
            'classrooms' => Classroom::orderBy('name')->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'isSecondaireClassroom' => $isSecondaireClassroom,
            'financialSummary' => $financialSummary,
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
