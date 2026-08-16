<?php

declare(strict_types=1);

namespace App\Livewire\Grading\GradeSheets\Primaire;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Support\RolePermissions;
use App\Domain\Grading\Models\GradeSheet;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Élèves — saisie des notes')]
class Students extends Component
{
    public GradeSheet $gradeSheet;

    public function mount(GradeSheet $gradeSheet): void
    {
        $this->authorize('viewAny', GradeSheet::class);

        $this->gradeSheet = $gradeSheet;
    }

    /**
     * Accès large (toutes classes primaire) : admin classique ou educateur —
     * voir RolePermissions::MATRIX['grades.enter']. Un enseignant simple ne
     * voit que les élèves des classes auxquelles il est affecté.
     */
    private function hasBroadGradeAccess(User $user): bool
    {
        return $user->hasAdminRightsOnCurrentEstablishment()
            || RolePermissions::can($user->currentRole(), 'grades.enter');
    }

    /**
     * @return Collection<int, Student>
     */
    private function students(): Collection
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $this->hasBroadGradeAccess($user);

        $assignedClassroomIds = $isAdmin
            ? null
            : TeacherAssignment::where('user_id', $user->id)
                ->whereHas('classroom.level', fn ($query) => $query->where('cycle', Cycle::Primaire))
                ->pluck('classroom_id');

        return Student::query()
            ->with(['enrollments' => fn ($query) => $query->where('status', 'active')->with('classroom')])
            ->whereHas('enrollments', function ($query) use ($assignedClassroomIds): void {
                $query->where('status', 'active')
                    ->whereHas('classroom.level', fn ($q) => $q->where('cycle', Cycle::Primaire));

                if ($assignedClassroomIds !== null) {
                    $query->whereIn('classroom_id', $assignedClassroomIds);
                }
            })
            ->orderBy('last_name')
            ->get();
    }

    public function render()
    {
        return view('livewire.grading.grade-sheets.primaire.students', [
            'students' => $this->students(),
        ]);
    }
}
