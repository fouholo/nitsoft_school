<?php

declare(strict_types=1);

namespace App\Livewire\GuardianPortal;

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Billing\Services\PaymentTrackingService;
use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Student;
use App\Livewire\GuardianPortal\Concerns\EnsuresGuardianAccess;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guardian-portal')]
#[Title('Mes enfants')]
class Dashboard extends Component
{
    use EnsuresGuardianAccess;

    public function mount(): void
    {
        $this->currentGuardian();
    }

    public function render()
    {
        $guardian = $this->currentGuardian();

        $students = $guardian->students()
            ->wherePivot('status', GuardianLinkStatus::Approved)
            ->with(['enrollments' => fn ($query) => $query
                ->where('status', 'active')
                ->latest('enrolled_on')
                ->with('classroom')])
            ->get();

        $pendingCount = $guardian->students()
            ->wherePivot('status', GuardianLinkStatus::Pending)
            ->count();

        return view('livewire.guardian-portal.dashboard', [
            'students' => $students,
            'pendingCount' => $pendingCount,
            'alerts' => $this->alertsFor($students),
        ]);
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return array<int, array{overdue: bool, recentAbsence: bool}>
     */
    private function alertsFor(Collection $students): array
    {
        $service = app(PaymentTrackingService::class);

        return $students->mapWithKeys(function (Student $student) use ($service) {
            $enrollment = $student->enrollments->first();

            $balance = $enrollment
                ? $service->balanceForStudent($student->id, $enrollment->school_year_id)
                : null;

            $recentAbsence = AttendanceRecord::query()
                ->where('student_id', $student->id)
                ->where('status', 'absent')
                ->whereHas('session', fn ($query) => $query->where('session_date', '>=', now()->subDays(7)))
                ->exists();

            return [$student->id => [
                'overdue' => ($balance['balance'] ?? 0) > 0,
                'recentAbsence' => $recentAbsence,
            ]];
        })->all();
    }
}
