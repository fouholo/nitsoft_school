<?php

declare(strict_types=1);

namespace App\Livewire\Billing\PaymentTracking;

use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\PaymentTrackingService;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Suivi des paiements')]
class Index extends Component
{
    public ?int $school_year_id = null;

    public ?int $levelFilter = null;

    public string $statusFilter = '';

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Payment::class);

        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
    }

    /**
     * @param  array{student_id: int, due_so_far: float, total_paid: float, balance: float}  $row
     */
    private function statusOf(array $row): string
    {
        return match (true) {
            $row['balance'] > 0 => 'late',
            $row['balance'] < 0 => 'advance',
            default => 'ontime',
        };
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        $ownerId = RolePermissions::can($user->currentRole(), 'finance.scope_own_only') ? $user->id : null;

        $balances = $this->school_year_id
            ? app(PaymentTrackingService::class)->balances($this->school_year_id, $ownerId)
            : collect();

        if ($this->levelFilter) {
            $studentIdsInLevel = Enrollment::where('school_year_id', $this->school_year_id)
                ->where('status', 'active')
                ->whereHas('classroom', fn ($query) => $query->where('level_id', $this->levelFilter))
                ->pluck('student_id');

            $balances = $balances->whereIn('student_id', $studentIdsInLevel);
        }

        $students = Student::whereIn('id', $balances->pluck('student_id'))
            ->with(['enrollments' => fn ($query) => $query
                ->where('school_year_id', $this->school_year_id)
                ->where('status', 'active')
                ->with('classroom')])
            ->get()
            ->keyBy('id');

        $allRows = $balances
            ->map(function (array $balance) use ($students) {
                $student = $students->get($balance['student_id']);
                $enrollment = $student?->enrollments->first();

                return [
                    ...$balance,
                    'student' => $student,
                    'enrollment' => $enrollment,
                    'classroom' => $enrollment?->classroom,
                ];
            })
            ->filter(fn (array $row) => $row['student'] !== null)
            ->sortByDesc('balance')
            ->values();

        $lateRows = $allRows->filter(fn (array $row) => $this->statusOf($row) === 'late');

        $rows = $allRows;

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $rows = $rows->filter(fn (array $row) => str_contains(
                mb_strtolower($row['student']->last_name.' '.$row['student']->first_name),
                $needle
            ));
        }

        if ($this->statusFilter !== '') {
            $rows = $rows->filter(fn (array $row) => $this->statusOf($row) === $this->statusFilter);
        }

        $rows = $rows->values();

        return view('livewire.billing.payment-tracking.index', [
            'rows' => $rows,
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'levels' => Level::orderBy('level_wording')->get(),
            'lateCount' => $lateRows->count(),
            'lateTotal' => (float) $lateRows->sum('balance'),
            'displayedDue' => (float) $rows->sum('due_so_far'),
            'displayedPaid' => (float) $rows->sum('total_paid'),
            'displayedBalance' => (float) $rows->sum('balance'),
        ]);
    }
}
